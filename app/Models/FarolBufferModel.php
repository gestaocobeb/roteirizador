<?php

namespace App\Models;

/**
 * Pedidos do Cora com o "Tipo buffer", para o Farol.
 */
class FarolBufferModel extends ImportavelModel
{
    /** Tipo buffer que conta como buffer no Farol (igual à planilha). */
    public const TIPO_BUFFER = 'REPROGRAMADO';

    /** Tipo pedido que não conta como buffer (a collation ignora acento: vale "REPOSIÇÃO"). */
    public const TIPO_PEDIDO_IGNORADO = 'REPOSICAO';

    protected string $table = 'farol_buffer';

    protected array $colunasImportacao = ['numero_pedido', 'cliente_codigo', 'nome_fantasia', 'tipo_pedido', 'tipo_buffer', 'setor_codigo', 'setor_descricao', 'volume_hl'];

    /**
     * Pedidos reprogramados da revenda, sem as reposições: por cliente e, dentro
     * do cliente, maior volume primeiro.
     */
    public function reprogramados(string $revenda): array
    {
        $stmt = $this->db->prepare(
            'SELECT numero_pedido, cliente_codigo, nome_fantasia, tipo_pedido, setor_codigo, setor_descricao, volume_hl
             FROM farol_buffer
             WHERE revenda = :revenda AND tipo_buffer = :tipo
               AND (tipo_pedido IS NULL OR tipo_pedido <> :reposicao)
             ORDER BY cliente_codigo, volume_hl DESC, numero_pedido'
        );
        $stmt->execute(['revenda' => $revenda, 'tipo' => self::TIPO_BUFFER, 'reposicao' => self::TIPO_PEDIDO_IGNORADO]);

        return $stmt->fetchAll();
    }

    /**
     * A revenda já teve o buffer importado (mesmo sem reprogramados)?
     */
    public function importado(string $revenda): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM farol_buffer WHERE revenda = :revenda LIMIT 1');
        $stmt->execute(['revenda' => $revenda]);

        return (bool) $stmt->fetchColumn();
    }
}
