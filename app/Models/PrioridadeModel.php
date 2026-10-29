<?php

namespace App\Models;

use App\Core\Model;

/**
 * Clientes priorizados pelo Comercial para a data de entrega.
 * O HL é sempre calculado a partir dos pedidos do Cora importados,
 * de modo que uma nova importação atualiza os totais automaticamente.
 */
class PrioridadeModel extends Model
{
    protected string $table = 'prioridades';

    /** HL do dia por cliente de cada revenda (pedidos do Cora). */
    private const SQL_HL = 'SELECT revenda, cliente_codigo, SUM(volume_hl) AS volume_hl,
                                   MAX(nome_fantasia) AS nome_fantasia, MAX(municipio) AS municipio
                            FROM pedidos_cora
                            WHERE data_referencia = :data2
                            GROUP BY revenda, cliente_codigo';

    /**
     * Uso de cada setor da Mesa: clientes priorizados e HL utilizado x limites.
     */
    public function usoPorSetor(string $data, int $mesa, ?string $revenda = null): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.codigo, s.revenda, s.limite_clientes, s.limite_hl,
                    COUNT(pr.id) AS clientes,
                    COALESCE(SUM(hl.volume_hl), 0) AS hl_utilizado
             FROM setores s
             LEFT JOIN prioridades pr ON pr.setor_codigo = s.codigo AND pr.data_referencia = :data1
             LEFT JOIN (' . self::SQL_HL . ') hl ON hl.revenda = pr.revenda AND hl.cliente_codigo = pr.cliente_codigo
             WHERE s.mesa = :mesa AND s.ativo = 1 AND (:todas = 1 OR s.revenda = :revenda)
             GROUP BY s.codigo, s.revenda, s.limite_clientes, s.limite_hl
             ORDER BY s.revenda, s.codigo'
        );
        $stmt->execute(['data1' => $data, 'data2' => $data, 'mesa' => $mesa, 'todas' => $revenda === null ? 1 : 0, 'revenda' => (string) $revenda]);

        return $stmt->fetchAll();
    }

    /**
     * Prioridades da data, opcionalmente filtradas por revenda e Mesa.
     */
    public function listar(string $data, ?string $revenda = null, ?int $mesa = null): array
    {
        $sql = 'SELECT pr.id, pr.revenda, pr.cliente_codigo, pr.setor_codigo, pr.observacao, pr.criado_em,
                       s.mesa,
                       COALESCE(hl.volume_hl, 0) AS volume_hl,
                       COALESCE(NULLIF(c.nome_fantasia, \'\'), hl.nome_fantasia) AS nome,
                       COALESCE(NULLIF(c.cidade, \'\'), hl.municipio) AS cidade,
                       tr.condicao_entrega AS trava_condicao,
                       u.nome AS usuario_nome
                FROM prioridades pr
                JOIN setores s ON s.codigo = pr.setor_codigo
                LEFT JOIN (' . self::SQL_HL . ') hl ON hl.revenda = pr.revenda AND hl.cliente_codigo = pr.cliente_codigo
                LEFT JOIN clientes c ON c.revenda = pr.revenda AND c.codigo = pr.cliente_codigo
                LEFT JOIN clientes_travas tr ON tr.revenda = pr.revenda AND tr.cliente_codigo = pr.cliente_codigo
                LEFT JOIN usuarios u ON u.id = pr.usuarios_id
                WHERE pr.data_referencia = :data1';

        $parametros = ['data1' => $data, 'data2' => $data];

        if ($revenda !== null) {
            $sql .= ' AND pr.revenda = :revenda';
            $parametros['revenda'] = $revenda;
        }

        if ($mesa !== null) {
            $sql .= ' AND s.mesa = :mesa';
            $parametros['mesa'] = $mesa;
        }

        $stmt = $this->db->prepare($sql . ' ORDER BY pr.revenda, s.mesa, pr.setor_codigo, pr.id');
        $stmt->execute($parametros);

        return $stmt->fetchAll();
    }

    public function existe(string $data, string $revenda, int $cliente): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM prioridades WHERE data_referencia = :data AND revenda = :revenda AND cliente_codigo = :cliente LIMIT 1'
        );
        $stmt->execute(['data' => $data, 'revenda' => $revenda, 'cliente' => $cliente]);

        return (bool) $stmt->fetchColumn();
    }

    public function adicionar(string $data, string $revenda, int $cliente, int $setor, ?string $observacao, int $usuarioId): string
    {
        return $this->create([
            'data_referencia' => $data,
            'revenda' => $revenda,
            'cliente_codigo' => $cliente,
            'setor_codigo' => $setor,
            'observacao' => $observacao,
            'usuarios_id' => $usuarioId,
        ]);
    }

    /**
     * Prioridade com a Mesa do setor, para checagem de permissão antes de remover.
     */
    public function buscarComMesa(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT pr.*, s.mesa FROM prioridades pr JOIN setores s ON s.codigo = pr.setor_codigo WHERE pr.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $prioridade = $stmt->fetch();

        return $prioridade ?: null;
    }

    public function totalNaData(string $data, ?string $revenda = null, ?int $mesa = null): int
    {
        $sql = 'SELECT COUNT(*) FROM prioridades pr JOIN setores s ON s.codigo = pr.setor_codigo WHERE pr.data_referencia = :data';
        $parametros = ['data' => $data];

        if ($revenda !== null) {
            $sql .= ' AND pr.revenda = :revenda';
            $parametros['revenda'] = $revenda;
        }

        if ($mesa !== null) {
            $sql .= ' AND s.mesa = :mesa';
            $parametros['mesa'] = $mesa;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn();
    }
}
