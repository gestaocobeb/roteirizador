<?php

namespace App\Models;

/**
 * Pedidos do Cora (pedidos_roteirizacao) por situação, para o Farol.
 */
class FarolBufferModel extends ImportavelModel
{
    /** Situação do pedido (coluna K) que é buffer. */
    public const BUFFER = 'REPROGRAMADO';

    /** Situações que contam como roteirizado. */
    public const ROTEIRIZADO = ['ENVIADO_HUB_FATURAMENTO', 'AGUARDANDO_ROTEIRIZACAO'];

    protected string $table = 'farol_buffer';

    protected array $colunasImportacao = ['numero_pedido', 'cliente_codigo', 'nome_fantasia', 'tipo_pedido', 'situacao_pedido', 'tipo_buffer', 'setor_codigo', 'setor_descricao', 'volume_hl'];

    /**
     * Buffer reprogramado da revenda: uma linha por cliente, com os pedidos dele
     * (maior volume primeiro) e o volume total. Ordem: setor (sem setor no fim),
     * volume total (maior primeiro) e cliente.
     * O arquivo do Cora não traz o setor: vem do Cadastro de clientes (Promax 01.20.11,
     * código e descrição) e, se o cliente não estiver lá, da última importação do Cora (Crítica GOD).
     */
    public function reprogramados(string $revenda): array
    {
        $stmt = $this->db->prepare(
            'SELECT GROUP_CONCAT(b.numero_pedido ORDER BY b.volume_hl DESC, b.numero_pedido SEPARATOR \', \') AS numero_pedido,
                    COUNT(*) AS pedidos,
                    b.cliente_codigo, MAX(b.nome_fantasia) AS nome_fantasia, SUM(b.volume_hl) AS volume_hl,
                    MAX(COALESCE(b.setor_codigo, c.setor_codigo, p.setor_codigo)) AS setor_codigo,
                    MAX(CASE WHEN c.setor_codigo IS NOT NULL THEN c.setor_descricao ELSE p.setor_descricao END) AS setor_descricao
             FROM farol_buffer b
             LEFT JOIN clientes c ON c.revenda = b.revenda AND c.codigo = b.cliente_codigo
             LEFT JOIN (
                 SELECT cliente_codigo, MAX(setor_codigo) AS setor_codigo, MAX(setor_descricao) AS setor_descricao
                 FROM pedidos_cora
                 WHERE revenda = :revenda_cora
                 GROUP BY cliente_codigo
             ) p ON p.cliente_codigo = b.cliente_codigo
             WHERE b.revenda = :revenda AND b.situacao_pedido = :buffer
             GROUP BY b.cliente_codigo
             ORDER BY MAX(COALESCE(b.setor_codigo, c.setor_codigo, p.setor_codigo)) IS NULL,
                      MAX(COALESCE(b.setor_codigo, c.setor_codigo, p.setor_codigo)), SUM(b.volume_hl) DESC, b.cliente_codigo'
        );
        $stmt->execute(['revenda' => $revenda, 'revenda_cora' => $revenda, 'buffer' => self::BUFFER]);

        return $stmt->fetchAll();
    }

    /**
     * Volume (HL) roteirizado da revenda (ENVIADO_HUB_FATURAMENTO + AGUARDANDO_ROTEIRIZACAO),
     * ou null se o arquivo do Cora ainda não foi importado.
     */
    public function volumeRoteirizado(string $revenda): ?float
    {
        if (!$this->importado($revenda)) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(volume_hl), 0) FROM farol_buffer
             WHERE revenda = :revenda AND situacao_pedido IN (:s1, :s2)'
        );
        $stmt->execute(['revenda' => $revenda, 's1' => self::ROTEIRIZADO[0], 's2' => self::ROTEIRIZADO[1]]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * A revenda já teve o arquivo novo do Cora importado (mesmo sem reprogramados)?
     * Registros do layout antigo (sem situação) não contam.
     */
    public function importado(string $revenda): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM farol_buffer WHERE revenda = :revenda AND situacao_pedido IS NOT NULL LIMIT 1');
        $stmt->execute(['revenda' => $revenda]);

        return (bool) $stmt->fetchColumn();
    }
}
