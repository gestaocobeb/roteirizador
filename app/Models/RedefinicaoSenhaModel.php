<?php

namespace App\Models;

use App\Core\Model;

/**
 * Tokens de "Esqueci minha senha". O token puro vai só no e-mail;
 * o banco guarda o hash SHA-256 (um vazamento da tabela não permite redefinir senhas).
 */
class RedefinicaoSenhaModel extends Model
{
    protected string $table = 'redefinicoes_senha';

    /**
     * Gera um token novo para o usuário, apagando os anteriores dele e os expirados.
     * Retorna o token puro (64 caracteres hexadecimais) para montar o link.
     */
    public function gerar(int $usuarioId): string
    {
        $token = bin2hex(random_bytes(32));

        $stmt = $this->db->prepare('DELETE FROM redefinicoes_senha WHERE usuarios_id = :id OR expira_em < NOW()');
        $stmt->execute(['id' => $usuarioId]);

        $stmt = $this->db->prepare(
            'INSERT INTO redefinicoes_senha (usuarios_id, token_hash, expira_em)
             VALUES (:id, :hash, NOW() + INTERVAL 1 HOUR)'
        );
        $stmt->execute(['id' => $usuarioId, 'hash' => hash('sha256', $token)]);

        return $token;
    }

    /**
     * Indica se o usuário já pediu um link nos últimos 2 minutos (evita e-mails em rajada).
     */
    public function pedidoRecente(int $usuarioId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM redefinicoes_senha
             WHERE usuarios_id = :id AND criado_em > NOW() - INTERVAL 2 MINUTE LIMIT 1'
        );
        $stmt->execute(['id' => $usuarioId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Usuário ativo dono de um token válido (não expirado), ou null.
     */
    public function usuarioDoToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.nome, u.email
             FROM redefinicoes_senha r
             JOIN usuarios u ON u.id = r.usuarios_id
             WHERE r.token_hash = :hash AND r.expira_em > NOW() AND u.ativo = 1
             LIMIT 1'
        );
        $stmt->execute(['hash' => hash('sha256', $token)]);
        $resultado = $stmt->fetch();

        return $resultado ?: null;
    }

    /**
     * Apaga os tokens do usuário (uso único: chamado após redefinir a senha).
     */
    public function invalidar(int $usuarioId): void
    {
        $stmt = $this->db->prepare('DELETE FROM redefinicoes_senha WHERE usuarios_id = :id');
        $stmt->execute(['id' => $usuarioId]);
    }
}
