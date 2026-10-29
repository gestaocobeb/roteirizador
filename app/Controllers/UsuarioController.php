<?php

namespace App\Controllers;

use App\Core\Logger;
use App\Helpers\Revenda;
use App\Models\MesaModel;
use App\Models\UsuarioModel;

/**
 * Gestão de usuários (perfil Administrador).
 */
class UsuarioController extends BaseController
{
    public function index(): void
    {
        $model = new UsuarioModel();

        $this->view('usuarios/index', [
            'usuarios' => $model->listar(),
            'abasPorUsuario' => $model->abasDeTodos(),
            'salasPorUsuario' => $model->mesasDeTodos(),
            'abas' => UsuarioModel::ABAS,
            'perfis' => UsuarioModel::PERFIS,
            'mesas' => $this->mesasComRevendas(),
            'revendas' => Revenda::siglas(),
        ]);
    }

    /**
     * Mesas para o cadastro do Comercial (código => revendas atendidas, ex.: 23 => "AB/LP").
     */
    private function mesasComRevendas(): array
    {
        $mesas = [];
        foreach ((new MesaModel())->listar() as $m) {
            $mesas[(int) $m['codigo']][] = $m['revenda'];
        }

        return array_map(fn ($revendas) => implode('/', $revendas), $mesas);
    }

    /**
     * Revenda da Logística ('' = todas). Para os demais perfis não se aplica.
     */
    private function revendaDoFormulario(string $perfil): ?string
    {
        $revenda = (string) $this->input('revenda', '');

        return $perfil === 'logistica' && Revenda::valida($revenda) ? $revenda : null;
    }

    public function revenda(string $id): void
    {
        $model = new UsuarioModel();
        $usuario = $model->buscar((int) $id);

        if (!$usuario || $usuario['perfil'] !== 'logistica') {
            $this->voltar('usuarios', 'danger', 'A revenda só é definida para usuários da Logística (o Comercial segue a revenda da Sala).');
        }

        $revenda = $this->revendaDoFormulario('logistica');
        $model->update((int) $id, ['revenda' => $revenda]);
        Logger::info("Revenda de {$usuario['email']} alterada para " . ($revenda ?? 'todas') . ' por ' . $this->usuario()['email']);

        $this->voltar('usuarios', 'success', "{$usuario['nome']}: " . ($revenda !== null ? "revenda {$revenda}." : 'todas as revendas.'));
    }

    /**
     * Salas (Mesas) marcadas no formulário: só códigos cadastrados, sem repetição.
     *
     * @return int[]
     */
    private function salasDoFormulario(): array
    {
        $salas = $_POST['salas'] ?? [];

        if (!is_array($salas)) {
            return [];
        }

        $validas = array_values(array_intersect((new MesaModel())->codigos(), array_map('intval', $salas)));
        sort($validas);

        return $validas;
    }

    /**
     * Altera as Salas de um usuário do Comercial (ao menos uma).
     */
    public function salas(string $id): void
    {
        $model = new UsuarioModel();
        $usuario = $model->buscar((int) $id);

        if (!$usuario || $usuario['perfil'] !== 'comercial') {
            $this->voltar('usuarios', 'danger', 'As Salas só são definidas para usuários do Comercial.');
        }

        $salas = $this->salasDoFormulario();

        if ($salas === []) {
            $this->voltar('usuarios', 'danger', "Marque ao menos uma Sala para {$usuario['nome']}.");
        }

        $model->definirMesas((int) $id, $salas);
        Logger::info("Salas de {$usuario['email']} alteradas para [" . implode(', ', $salas) . '] por ' . $this->usuario()['email']);

        $this->voltar('usuarios', 'success', "{$usuario['nome']}: Sala(s) " . implode(', ', $salas) . '.');
    }

    /**
     * Abas marcadas no formulário (lista de chaves válidas).
     */
    private function abasDoFormulario(): array
    {
        $abas = $_POST['abas'] ?? [];

        return is_array($abas)
            ? array_values(array_intersect(array_keys(UsuarioModel::ABAS), array_map('strval', $abas)))
            : [];
    }

    public function abas(string $id): void
    {
        $model = new UsuarioModel();
        $usuario = $model->buscar((int) $id);

        if (!$usuario) {
            $this->voltar('usuarios', 'danger', 'Usuário não encontrado.');
        }

        if ($usuario['perfil'] === 'admin') {
            $this->voltar('usuarios', 'info', 'Administradores têm sempre acesso a todas as abas.');
        }

        $abas = $this->abasDoFormulario();
        $model->definirAbas((int) $id, $usuario['perfil'], $abas);
        Logger::info("Abas de {$usuario['email']} alteradas para [" . implode(', ', $abas) . '] por ' . $this->usuario()['email']);

        $this->voltar('usuarios', 'success', "Abas de {$usuario['nome']} atualizadas.");
    }

    public function criar(): void
    {
        $nome = mb_substr((string) $this->input('nome', ''), 0, 150);
        $email = mb_strtolower((string) $this->input('email', ''));
        $senha = (string) ($_POST['senha'] ?? '');
        $perfil = (string) $this->input('perfil', '');
        $salas = $this->salasDoFormulario();
        $model = new UsuarioModel();

        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->voltar('usuarios', 'danger', 'Informe nome e um e-mail válido.');
        }

        if (!array_key_exists($perfil, UsuarioModel::PERFIS)) {
            $this->voltar('usuarios', 'danger', 'Perfil inválido.');
        }

        if ($perfil === 'comercial' && $salas === []) {
            $this->voltar('usuarios', 'danger', 'Usuário do Comercial precisa estar vinculado a ao menos uma Sala cadastrada.');
        }

        if (mb_strlen($senha) < 8) {
            $this->voltar('usuarios', 'danger', 'A senha inicial deve ter ao menos 8 caracteres.');
        }

        if ($model->buscarPorEmail($email)) {
            $this->voltar('usuarios', 'warning', 'Já existe um usuário com este e-mail.');
        }

        $revenda = $this->revendaDoFormulario($perfil);
        $id = (int) $model->criar($nome, $email, $senha, $perfil, $revenda);
        $model->definirAbas($id, $perfil, $this->abasDoFormulario());
        if ($perfil === 'comercial') {
            $model->definirMesas($id, $salas);
        }
        Logger::info("Usuário criado: {$email} ({$perfil}" . ($revenda ? ", {$revenda}" : '') . ($perfil === 'comercial' ? ', Salas ' . implode('/', $salas) : '') . ') por ' . $this->usuario()['email']);

        $this->voltar('usuarios', 'success', "Usuário {$nome} criado.");
    }

    public function status(string $id): void
    {
        $model = new UsuarioModel();
        $usuario = $model->buscar((int) $id);

        if (!$usuario) {
            $this->voltar('usuarios', 'danger', 'Usuário não encontrado.');
        }

        if ((int) $usuario['id'] === (int) $this->usuario()['id']) {
            $this->voltar('usuarios', 'warning', 'Você não pode desativar o próprio usuário.');
        }

        $ativar = (int) $usuario['ativo'] !== 1;
        $model->alterarStatus((int) $id, $ativar);
        Logger::info("Usuário {$usuario['email']} " . ($ativar ? 'ativado' : 'desativado') . ' por ' . $this->usuario()['email']);

        $this->voltar('usuarios', 'success', "Usuário {$usuario['nome']} " . ($ativar ? 'ativado.' : 'desativado.'));
    }

    public function senha(string $id): void
    {
        $model = new UsuarioModel();
        $usuario = $model->buscar((int) $id);
        $senha = (string) ($_POST['senha'] ?? '');

        if (!$usuario) {
            $this->voltar('usuarios', 'danger', 'Usuário não encontrado.');
        }

        if (mb_strlen($senha) < 8) {
            $this->voltar('usuarios', 'danger', 'A nova senha deve ter ao menos 8 caracteres.');
        }

        $model->alterarSenha((int) $id, $senha);
        Logger::info("Senha redefinida para {$usuario['email']} por " . $this->usuario()['email']);

        $this->voltar('usuarios', 'success', "Senha de {$usuario['nome']} redefinida.");
    }
}
