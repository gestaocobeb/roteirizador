<?php

namespace App\Models;

use App\Core\Model;

/**
 * Justificativa da Logística por solicitação de troca (revenda + solicitação + cliente).
 * Não é apagada nas reimportações do 03.18.05.
 */
class TrocaJustificativaModel extends Model
{
    protected string $table = 'trocas_justificativas';

    /**
     * Grava ou substitui a justificativa da solicitação.
     */
    public function salvar(string $revenda, string $solicitacao, int $cliente, string $justificativa, int $usuarioId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO trocas_justificativas (revenda, solicitacao, cliente_codigo, justificativa, usuarios_id)
             VALUES (:revenda, :solicitacao, :cliente, :justificativa, :usuario)
             ON DUPLICATE KEY UPDATE justificativa = VALUES(justificativa), usuarios_id = VALUES(usuarios_id)'
        );

        return $stmt->execute([
            'revenda' => $revenda,
            'solicitacao' => $solicitacao,
            'cliente' => $cliente,
            'justificativa' => $justificativa,
            'usuario' => $usuarioId,
        ]);
    }

    public function remover(string $revenda, string $solicitacao, int $cliente): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM trocas_justificativas WHERE revenda = :revenda AND solicitacao = :solicitacao AND cliente_codigo = :cliente'
        );

        return $stmt->execute(['revenda' => $revenda, 'solicitacao' => $solicitacao, 'cliente' => $cliente]);
    }
}
