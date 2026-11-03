<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Session;
use App\Helpers\Email;
use App\Models\RedefinicaoSenhaModel;
use App\Models\UsuarioModel;

/**
 * Login, logout, criação do primeiro administrador e "Esqueci minha senha".
 */
class AuthController extends BaseController
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            $this->redirect(base_url());
        }

        if ((new UsuarioModel())->total() === 0) {
            $this->redirect(base_url('primeiro-acesso'));
        }

        $this->view('auth/login');
    }

    public function login(): void
    {
        $email = (string) $this->input('email', '');
        // A senha não passa por trim(): espaços fazem parte dela.
        $senha = (string) ($_POST['senha'] ?? '');

        if ($email === '' || $senha === '' || !Auth::attempt($email, $senha)) {
            $this->voltar('login', 'danger', 'E-mail ou senha inválidos.');
        }

        $usuario = (new UsuarioModel())->buscar(Auth::id());

        if (!$usuario || (int) $usuario['ativo'] !== 1) {
            Auth::logout();
            Session::start();
            $this->voltar('login', 'danger', 'Usuário inativo. Procure o administrador do sistema.');
        }

        Logger::info("Login realizado: {$email}");
        $this->redirect(base_url());
    }

    public function logout(): void
    {
        Auth::logout();
        Session::start();
        $this->voltar('login', 'success', 'Sessão encerrada.');
    }

    public function primeiroAcessoForm(): void
    {
        if ((new UsuarioModel())->total() > 0) {
            $this->redirect(base_url('login'));
        }

        $this->view('auth/primeiro-acesso');
    }

    public function primeiroAcesso(): void
    {
        $model = new UsuarioModel();

        if ($model->total() > 0) {
            $this->redirect(base_url('login'));
        }

        $nome = mb_substr((string) $this->input('nome', ''), 0, 150);
        $email = mb_strtolower((string) $this->input('email', ''));
        $senha = (string) ($_POST['senha'] ?? '');
        $confirmacao = (string) ($_POST['senha_confirmacao'] ?? '');

        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->voltar('primeiro-acesso', 'danger', 'Informe nome e um e-mail válido.');
        }

        if (mb_strlen($senha) < 8 || $senha !== $confirmacao) {
            $this->voltar('primeiro-acesso', 'danger', 'A senha deve ter ao menos 8 caracteres e ser igual à confirmação.');
        }

        $model->criar($nome, $email, $senha, 'admin', null);
        Logger::info("Primeiro administrador criado: {$email}");

        Auth::attempt($email, $senha);
        $this->voltar('', 'success', 'Administrador criado. Comece cadastrando os usuários e importando os dados.');
    }

    public function esqueciSenhaForm(): void
    {
        if (Auth::check()) {
            $this->redirect(base_url());
        }

        $this->view('auth/esqueci-senha');
    }

    /**
     * Envia o link de redefinição. A resposta é sempre a mesma, exista ou não o e-mail,
     * para não revelar quais e-mails estão cadastrados.
     */
    public function esqueciSenha(): void
    {
        $email = mb_strtolower((string) $this->input('email', ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->voltar('esqueci-senha', 'danger', 'Informe um e-mail válido.');
        }

        $usuario = (new UsuarioModel())->buscarPorEmail($email);
        $redefinicoes = new RedefinicaoSenhaModel();

        if ($usuario && (int) $usuario['ativo'] === 1 && !$redefinicoes->pedidoRecente((int) $usuario['id'])) {
            $this->enviarLinkRedefinicao($usuario, $redefinicoes);
        } elseif ($usuario) {
            Logger::warning("Esqueci minha senha ignorado (usuário inativo ou pedido recente): {$email}");
        }

        $this->voltar('login', 'success', 'Se o e-mail estiver cadastrado, você receberá em instantes um link para criar uma nova senha. O link vale por 1 hora.');
    }

    public function redefinirSenhaForm(string $token): void
    {
        $usuario = $this->usuarioDoToken($token);

        $this->view('auth/redefinir-senha', ['token' => $token, 'email' => $usuario['email']]);
    }

    public function redefinirSenha(): void
    {
        $token = (string) ($_POST['token'] ?? '');
        $usuario = $this->usuarioDoToken($token);
        $senha = (string) ($_POST['senha'] ?? '');
        $confirmacao = (string) ($_POST['senha_confirmacao'] ?? '');

        if (mb_strlen($senha) < 8 || $senha !== $confirmacao) {
            $this->voltar('redefinir-senha/' . $token, 'danger', 'A senha deve ter ao menos 8 caracteres e ser igual à confirmação.');
        }

        (new UsuarioModel())->alterarSenha((int) $usuario['id'], $senha);
        (new RedefinicaoSenhaModel())->invalidar((int) $usuario['id']);
        Logger::info("Senha redefinida pelo link de e-mail: {$usuario['email']}");

        $this->voltar('login', 'success', 'Senha alterada. Entre com a nova senha.');
    }

    private function enviarLinkRedefinicao(array $usuario, RedefinicaoSenhaModel $redefinicoes): void
    {
        $urlBase = rtrim((string) config('app.url'), '/');

        if ($urlBase === '') {
            Logger::error('Esqueci minha senha: APP_URL não configurado no .env; link não enviado.');
            return;
        }

        $token = $redefinicoes->gerar((int) $usuario['id']);
        $link = $urlBase . '/redefinir-senha/' . $token;

        // Somente em desenvolvimento: grava o link no log para testar sem SMTP.
        // Em produção o link nunca é registrado (daria acesso à conta a quem lê o log).
        if (config('app.env') === 'development') {
            Logger::info("[DESENVOLVIMENTO] Link de redefinição de senha de {$usuario['email']}: {$link}");
            Email::enviar($usuario['email'], $usuario['nome'], 'Roteirizador - Redefinição de senha', 'emails/redefinir-senha', ['nome' => $usuario['nome'], 'link' => $link]);
            return;
        }

        $enviado = Email::enviar(
            $usuario['email'],
            $usuario['nome'],
            'Roteirizador - Redefinição de senha',
            'emails/redefinir-senha',
            ['nome' => $usuario['nome'], 'link' => $link]
        );

        if ($enviado) {
            Logger::info("Link de redefinição de senha enviado: {$usuario['email']}");
        } else {
            // Sem e-mail entregue, o token não serve para nada: libera um novo pedido na hora.
            $redefinicoes->invalidar((int) $usuario['id']);
        }
    }

    /**
     * Usuário dono do token ou volta para "Esqueci minha senha" se o link for inválido/expirado.
     */
    private function usuarioDoToken(string $token): array
    {
        $usuario = preg_match('/^[a-f0-9]{64}$/', $token) ? (new RedefinicaoSenhaModel())->usuarioDoToken($token) : null;

        if (!$usuario) {
            $this->voltar('esqueci-senha', 'danger', 'Link inválido ou expirado. Solicite um novo.');
        }

        return $usuario;
    }
}
