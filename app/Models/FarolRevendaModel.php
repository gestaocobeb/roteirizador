<?php

namespace App\Models;

use App\Core\Model;

/**
 * Dados digitados no Farol por revenda (observações de Spot e recarga).
 */
class FarolRevendaModel extends Model
{
    protected string $table = 'farol_revendas';

    public function daRevenda(string $revenda): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT f.volume_vendido_hl, f.observacao_spot, f.observacao_recarga, f.atualizado_em, u.nome AS usuario_nome
             FROM farol_revendas f
             JOIN usuarios u ON u.id = f.usuarios_id
             WHERE f.revenda = :revenda'
        );
        $stmt->execute(['revenda' => $revenda]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Grava as observações. A coluna volume_vendido_hl ficou sem uso: o volume vendido
     * agora sai do arquivo do Cora (FarolBufferModel).
     */
    public function salvar(string $revenda, ?string $obsSpot, ?string $obsRecarga, int $usuarioId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO farol_revendas (revenda, observacao_spot, observacao_recarga, usuarios_id)
             VALUES (:revenda, :spot, :recarga, :usuario)
             ON DUPLICATE KEY UPDATE observacao_spot = VALUES(observacao_spot),
                 observacao_recarga = VALUES(observacao_recarga),
                 usuarios_id = VALUES(usuarios_id)'
        );
        $stmt->execute([
            'revenda' => $revenda,
            'spot' => $obsSpot,
            'recarga' => $obsRecarga,
            'usuario' => $usuarioId,
        ]);
    }
}
