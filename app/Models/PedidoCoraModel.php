<?php

namespace App\Models;

/**
 * Pedidos do Cora aguardando roteirização, por data de entrega.
 * Base da Crítica GOD e do cálculo de HL das prioridades.
 */
class PedidoCoraModel extends ImportavelModel
{
    protected string $table = 'pedidos_cora';

    protected array $colunasImportacao = [
        'numero_pedido', 'tipo_pedido', 'situacao_pedido', 'situacao_atendimento', 'municipio',
        'cliente_codigo', 'cliente_nome', 'nome_fantasia', 'setor_codigo', 'setor_descricao',
        'gerente_codigo', 'valor_total', 'volume_hl', 'produto_descricao', 'critica_fora_rota', 'criticas',
    ];

    /**
     * Substitui os pedidos de uma data de entrega da revenda pelos do novo export.
     */
    public function substituirData(string $data, string $revenda, array $registros): int
    {
        $this->db->beginTransaction();

        try {
            $this->excluirData($data, $revenda);
            $total = $this->inserirLote($registros, ['data_referencia' => $data, 'revenda' => $revenda]);
            $this->db->commit();

            return $total;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Apaga os pedidos de uma data de entrega da revenda (exclusão da importação
     * do Cora). Prioridades e decisões da data são mantidas.
     *
     * @return int Quantidade de linhas apagadas
     */
    public function excluirData(string $data, string $revenda): int
    {
        $stmt = $this->db->prepare('DELETE FROM pedidos_cora WHERE data_referencia = :data AND revenda = :revenda');
        $stmt->execute(['data' => $data, 'revenda' => $revenda]);

        return $stmt->rowCount();
    }

    public function ultimaData(?string $revenda = null): ?string
    {
        $stmt = $this->db->prepare('SELECT MAX(data_referencia) FROM pedidos_cora WHERE :todas = 1 OR revenda = :revenda');
        $stmt->execute(['todas' => $revenda === null ? 1 : 0, 'revenda' => (string) $revenda]);
        $data = $stmt->fetchColumn();

        return $data ?: null;
    }

    public function totalClientes(string $data, ?string $revenda = null, ?int $mesa = null): int
    {
        $sql = 'SELECT COUNT(DISTINCT revenda, cliente_codigo) FROM pedidos_cora WHERE data_referencia = :data';
        $parametros = ['data' => $data];

        if ($revenda !== null) {
            $sql .= ' AND revenda = :revenda';
            $parametros['revenda'] = $revenda;
        }

        if ($mesa !== null) {
            $sql .= ' AND gerente_codigo = :mesa';
            $parametros['mesa'] = $mesa;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Uma linha por cliente com todos os indicadores da Crítica GOD.
     *
     * O valor do pedido é somado por pedido distinto, pois o export itemizado
     * do Cora repete o "Valor total" do pedido em cada item.
     */
    public function resumoCritica(string $data, ?string $revenda = null, ?int $mesa = null): array
    {
        $sql = "SELECT p.revenda,
                       p.cliente_codigo,
                       COALESCE(NULLIF(c.nome_fantasia, ''), p.nome_fantasia) AS nome,
                       COALESCE(NULLIF(c.razao_social, ''), p.cliente_nome) AS razao_social,
                       COALESCE(NULLIF(c.cidade, ''), p.municipio) AS cidade,
                       COALESCE(c.setor_codigo, p.setor_codigo) AS setor_codigo,
                       c.dia_visita,
                       p.gerente_codigo,
                       p.qtd_pedidos,
                       p.volume_hl,
                       p.fora_rota,
                       p.criticas,
                       v.valor_pedido,
                       (tl.id IS NOT NULL) AS top_line,
                       COALESCE(bl.ocorrencias, 0) AS black_list,
                       tr.condicao_entrega AS trava_condicao,
                       tr.motivo AS trava_motivo,
                       COALESCE(h.quantidade, 0) AS compras,
                       d.decisao,
                       d.observacao AS decisao_observacao,
                       d.atualizado_em AS decidido_em,
                       u.nome AS decidido_por
                FROM (
                    SELECT revenda,
                           cliente_codigo,
                           MAX(nome_fantasia) AS nome_fantasia,
                           MAX(cliente_nome) AS cliente_nome,
                           MAX(municipio) AS municipio,
                           MAX(setor_codigo) AS setor_codigo,
                           MAX(gerente_codigo) AS gerente_codigo,
                           COUNT(DISTINCT numero_pedido) AS qtd_pedidos,
                           SUM(volume_hl) AS volume_hl,
                           MAX(critica_fora_rota) AS fora_rota,
                           MAX(criticas) AS criticas
                    FROM pedidos_cora
                    WHERE data_referencia = :data1
                    GROUP BY revenda, cliente_codigo
                ) p
                JOIN (
                    SELECT revenda, cliente_codigo, SUM(valor_pedido) AS valor_pedido
                    FROM (
                        SELECT revenda, cliente_codigo, numero_pedido, MAX(valor_total) AS valor_pedido
                        FROM pedidos_cora
                        WHERE data_referencia = :data2
                        GROUP BY revenda, cliente_codigo, numero_pedido
                    ) pp
                    GROUP BY revenda, cliente_codigo
                ) v ON v.revenda = p.revenda AND v.cliente_codigo = p.cliente_codigo
                LEFT JOIN clientes c ON c.revenda = p.revenda AND c.codigo = p.cliente_codigo
                LEFT JOIN clientes_top_line tl ON tl.revenda = p.revenda AND tl.cliente_codigo = p.cliente_codigo
                LEFT JOIN clientes_black_list bl ON bl.revenda = p.revenda AND bl.cliente_codigo = p.cliente_codigo
                LEFT JOIN clientes_travas tr ON tr.revenda = p.revenda AND tr.cliente_codigo = p.cliente_codigo
                LEFT JOIN historico_compras h ON h.revenda = p.revenda AND h.cliente_codigo = p.cliente_codigo
                LEFT JOIN criticas_decisoes d ON d.revenda = p.revenda AND d.cliente_codigo = p.cliente_codigo AND d.data_referencia = :data3
                LEFT JOIN usuarios u ON u.id = d.usuarios_id
                WHERE 1 = 1";

        $parametros = ['data1' => $data, 'data2' => $data, 'data3' => $data];

        if ($revenda !== null) {
            $sql .= ' AND p.revenda = :revenda';
            $parametros['revenda'] = $revenda;
        }

        if ($mesa !== null) {
            $sql .= ' AND p.gerente_codigo = :mesa';
            $parametros['mesa'] = $mesa;
        }

        $stmt = $this->db->prepare($sql . ' ORDER BY p.revenda, p.cliente_codigo');
        $stmt->execute($parametros);

        return $stmt->fetchAll();
    }

    /**
     * Dados do cliente necessários para priorizar ou decidir: setor e Mesa
     * informados no Cora e HL total do dia. Null se não houver pedido na data.
     */
    public function dadosCliente(string $data, string $revenda, int $cliente): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT revenda,
                    cliente_codigo,
                    MAX(nome_fantasia) AS nome_fantasia,
                    MAX(setor_codigo) AS setor_codigo,
                    MAX(gerente_codigo) AS gerente_codigo,
                    SUM(volume_hl) AS volume_hl
             FROM pedidos_cora
             WHERE data_referencia = :data AND revenda = :revenda AND cliente_codigo = :cliente
             GROUP BY revenda, cliente_codigo'
        );
        $stmt->execute(['data' => $data, 'revenda' => $revenda, 'cliente' => $cliente]);
        $dados = $stmt->fetch();

        return $dados ?: null;
    }

    /**
     * Mapa Sinergia: quantidade de PDVs e HL por cidade, a partir do resumo da Crítica.
     */
    public static function sinergiaPorCidade(array $resumo): array
    {
        $cidades = [];

        foreach ($resumo as $linha) {
            $cidade = trim((string) $linha['cidade']) ?: 'SEM CIDADE';
            $cidades[$cidade] ??= ['cidade' => $cidade, 'pdvs' => 0, 'hl' => 0.0, 'valor' => 0.0];
            $cidades[$cidade]['pdvs']++;
            $cidades[$cidade]['hl'] += (float) $linha['volume_hl'];
            $cidades[$cidade]['valor'] += (float) $linha['valor_pedido'];
        }

        usort($cidades, fn ($a, $b) => [$b['pdvs'], $b['hl']] <=> [$a['pdvs'], $a['hl']]);

        return $cidades;
    }
}
