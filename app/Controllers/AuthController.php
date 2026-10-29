<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Session;
use App\Models\UsuarioModel;

/**
 * Login, logout e criação do primeiro administrador.
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
}
