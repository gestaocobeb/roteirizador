<?php

namespace App\Models;

use App\Core\Model;

/**
 * Dados digitados no Farol por revenda: observações de Spot e recarga e o ajuste
 * manual das quantidades de mapas FF, Spot e recarga.
 */
class FarolRevendaModel extends Model
{
    protected string $table = 'farol_revendas';

    public function daRevenda(string $revenda): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT f.volume_vendido_hl, f.observacao_spot, f.observacao_recarga,
                    f.mapas_ff_ajuste, f.mapas_spot_ajuste, f.recargas_ajuste, f.ajuste_data,
                    f.atualizado_em, u.nome AS usuario_nome
             FROM farol_revendas f
             JOIN usuarios u ON u.id = f.usuarios_id
             WHERE f.revenda = :revenda'
        );
        $stmt->execute(['revenda' => $revenda]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Grava as observações e os ajustes (null = usar a quantidade do 03.11.49.02).
     * A coluna volume_vendido_hl ficou sem uso: o volume vendido sai do arquivo do Cora.
     *
     * @param array{ff: ?int, spot: ?int, recarga: ?int} $ajustes
     * @param string|null $ajusteData Entrega dos mapas a que os ajustes se referem
     */
    public function salvar(string $revenda, ?string $obsSpot, ?string $obsRecarga, array $ajustes, ?string $ajusteData, int $usuarioId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO farol_revendas (revenda, observacao_spot, observacao_recarga,
                    mapas_ff_ajuste, mapas_spot_ajuste, recargas_ajuste, ajuste_data, usuarios_id)
             VALUES (:revenda, :spot, :recarga, :ff_ajuste, :spot_ajuste, :recarga_ajuste, :ajuste_data, :usuario)
             ON DUPLICATE KEY UPDATE observacao_spot = VALUES(observacao_spot),
                 observacao_recarga = VALUES(observacao_recarga),
                 mapas_ff_ajuste = VALUES(mapas_ff_ajuste),
                 mapas_spot_ajuste = VALUES(mapas_spot_ajuste),
                 recargas_ajuste = VALUES(recargas_ajuste),
                 ajuste_data = VALUES(ajuste_data),
                 usuarios_id = VALUES(usuarios_id)'
        );
        $stmt->execute([
            'revenda' => $revenda,
            'spot' => $obsSpot,
            'recarga' => $obsRecarga,
            'ff_ajuste' => $ajustes['ff'],
            'spot_ajuste' => $ajustes['spot'],
            'recarga_ajuste' => $ajustes['recarga'],
            'ajuste_data' => $ajusteData,
            'usuario' => $usuarioId,
        ]);
    }
}
