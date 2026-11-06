<?php

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;

class UsuarioModel extends Model
{
    protected string $table = 'usuarios';

    public const PERFIS = [
        'admin' => 'Administrador',
        'logistica' => 'Logística',
        'comercial' => 'Comercial',
    ];

    /**
     * Abas (telas) do sistema. A chave é o primeiro segmento da rota.
     * "perfis" limita para quais perfis a aba pode ser liberada.
     */
    public const ABAS = [
        'critica' => ['rotulo' => 'Crítica GOD', 'icone' => 'bi-clipboard-check', 'perfis' => ['admin', 'logistica', 'comercial']],
        'prioridade' => ['rotulo' => 'Prioridade', 'icone' => 'bi-flag', 'perfis' => ['admin', 'logistica', 'comercial']],
        'consulta' => ['rotulo' => 'Consulta do dia', 'icone' => 'bi-truck', 'perfis' => ['admin', 'logistica', 'comercial']],
        'troca' => ['rotulo' => 'Troca', 'icone' => 'bi-arrow-left-right', 'perfis' => ['admin', 'logistica', 'comercial']],
        'aprovacao-troca' => ['rotulo' => 'Aprovação troca', 'icone' => 'bi-check2-square', 'perfis' => ['admin', 'logistica']],
        'importacoes' => ['rotulo' => 'Importações', 'icone' => 'bi-upload', 'perfis' => ['admin', 'logistica']],
        'setores' => ['rotulo' => 'Setores', 'icone' => 'bi-sliders', 'perfis' => ['admin', 'logistica']],
        'bees-loat' => ['rotulo' => 'Bees Loat', 'icone' => 'bi-box-seam', 'perfis' => ['admin', 'logistica', 'comercial']],
        'farol' => ['rotulo' => 'Farol', 'icone' => 'bi-stoplights', 'perfis' => ['admin', 'logistica']],
        'usuarios' => ['rotulo' => 'Usuários', 'icone' => 'bi-people', 'perfis' => ['admin']],
    ];

    /**
     * Abas que o perfil permite liberar.
     */
    public static function abasDoPerfil(string $perfil): array
    {
        return array_keys(array_filter(self::ABAS, fn ($aba) => in_array($perfil, $aba['perfis'], true)));
    }

    /**
     * Abas efetivamente liberadas para o usuário: Administrador tem todas;
     * os demais, as marcadas pelo Administrador dentro do que o perfil permite.
     */
    public function abasLiberadas(array $usuario): array
    {
        $permitidas = self::abasDoPerfil((string) $usuario['perfil']);

        if ($usuario['perfil'] === 'admin') {
            return $permitidas;
        }

        $stmt = $this->db->prepare('SELECT aba FROM usuarios_abas WHERE usuarios_id = :id');
        $stmt->execute(['id' => $usuario['id']]);
        $marcadas = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Mantém a ordem do menu.
        return array_values(array_intersect($permitidas, $marcadas));
    }

    /**
     * Abas marcadas de todos os usuários (id => [abas]), para a tela de Usuários.
     */
    public function abasDeTodos(): array
    {
        $resultado = [];
        foreach ($this->db->query('SELECT usuarios_id, aba FROM usuarios_abas')->fetchAll() as $linha) {
            $resultado[(int) $linha['usuarios_id']][] = $linha['aba'];
        }

        return $resultado;
    }

    /**
     * Substitui as abas do usuário, descartando as que o perfil não permite.
     */
    public function definirAbas(int $id, string $perfil, array $abas): void
    {
        $abas = array_values(array_intersect(self::abasDoPerfil($perfil), $abas));

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('DELETE FROM usuarios_abas WHERE usuarios_id = :id');
            $stmt->execute(['id' => $id]);

            $stmt = $this->db->prepare('INSERT INTO usuarios_abas (usuarios_id, aba) VALUES (:id, :aba)');
            foreach ($abas as $aba) {
                $stmt->execute(['id' => $id, 'aba' => $aba]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Busca o usuário sem o hash da senha (seguro para repassar às Views).
     */
    public function buscar($id): ?array
    {
        $usuario = $this->find($id);

        if ($usuario) {
            unset($usuario['senha']);
        }

        return $usuario;
    }

    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT id, nome, email, ativo FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $resultado = $stmt->fetch();

        return $resultado ?: null;
    }

    public function listar(): array
    {
        $stmt = $this->db->query(
            'SELECT u.id, u.nome, u.email, u.perfil, u.ativo, u.criado_em,
                    COALESCE(GROUP_CONCAT(DISTINCT m.revenda ORDER BY m.revenda SEPARATOR \'/\'), u.revenda) AS revenda
             FROM usuarios u
             LEFT JOIN usuarios_mesas um ON u.perfil = \'comercial\' AND um.usuarios_id = u.id
             LEFT JOIN mesas m ON m.codigo = um.mesa
             GROUP BY u.id, u.nome, u.email, u.perfil, u.ativo, u.criado_em, u.revenda
             ORDER BY u.nome'
        );
        return $stmt->fetchAll();
    }

    /**
     * Mesas (Salas) do usuário, em ordem.
     *
     * @return int[]
     */
    public function mesasDe(int $id): array
    {
        $stmt = $this->db->prepare('SELECT mesa FROM usuarios_mesas WHERE usuarios_id = :id ORDER BY mesa');
        $stmt->execute(['id' => $id]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Mesas de todos os usuários (id => [mesas]), para a tela de Usuários.
     */
    public function mesasDeTodos(): array
    {
        $resultado = [];
        foreach ($this->db->query('SELECT usuarios_id, mesa FROM usuarios_mesas ORDER BY mesa')->fetchAll() as $linha) {
            $resultado[(int) $linha['usuarios_id']][] = (int) $linha['mesa'];
        }

        return $resultado;
    }

    /**
     * Substitui as Mesas do usuário (lista já validada pelo Controller).
     */
    public function definirMesas(int $id, array $mesas): void
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('DELETE FROM usuarios_mesas WHERE usuarios_id = :id');
            $stmt->execute(['id' => $id]);

            $stmt = $this->db->prepare('INSERT INTO usuarios_mesas (usuarios_id, mesa) VALUES (:id, :mesa)');
            foreach (array_unique($mesas) as $mesa) {
                $stmt->execute(['id' => $id, 'mesa' => $mesa]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function total(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    }

    public function criar(string $nome, string $email, string $senha, string $perfil, ?string $revenda = null): string
    {
        return $this->create([
            'nome' => $nome,
            'email' => $email,
            'senha' => Auth::hashPassword($senha),
            'perfil' => $perfil,
            'revenda' => $revenda,
        ]);
    }

    public function alterarSenha(int $id, string $senha): bool
    {
        return $this->update($id, ['senha' => Auth::hashPassword($senha)]);
    }

    public function alterarStatus(int $id, bool $ativo): bool
    {
        return $this->update($id, ['ativo' => $ativo ? 1 : 0]);
    }
}
