<?php

namespace App\Models;

/**
 * Notas de troca em rota (Promax 03.02.37), por item.
 * O setor vem do relatório; quando ausente, do cadastro de clientes (01.20.11).
 */
class TrocaNotaModel extends ImportavelModel
{
    protected string $table = 'trocas_notas';

    protected array $colunasImportacao = [
        'operacao', 'data_operacao', 'data_emissao', 'nota', 'serie', 'cliente_codigo', 'cliente_nome',
        'produto_codigo', 'produto_descricao', 'unidade', 'quantidade', 'valor_total', 'mapa',
        'numero_pedido', 'status_nfe', 'setor_codigo',
    ];

    private const SQL_BASE = 'FROM trocas_notas n
                LEFT JOIN clientes c ON c.revenda = n.revenda AND c.codigo = n.cliente_codigo
                LEFT JOIN setores s ON s.codigo = COALESCE(n.setor_codigo, c.setor_codigo)
                WHERE (:todas = 1 OR n.revenda = :revenda)';

    /**
     * Itens das notas, filtrados por revenda, Mesa (setores da Mesa) e setor.
     */
    public function listar(?string $revenda, ?int $mesa, ?int $setor): array
    {
        $sql = 'SELECT n.*, COALESCE(n.setor_codigo, c.setor_codigo) AS setor, s.mesa ' . self::SQL_BASE;
        $parametros = ['todas' => $revenda === null ? 1 : 0, 'revenda' => (string) $revenda];

        if ($mesa !== null) {
            $sql .= ' AND s.mesa = :mesa';
            $parametros['mesa'] = $mesa;
        }

        if ($setor !== null) {
            $sql .= ' AND COALESCE(n.setor_codigo, c.setor_codigo) = :setor';
            $parametros['setor'] = $setor;
        }

        $stmt = $this->db->prepare($sql . ' ORDER BY setor, n.revenda, n.nota, n.id');
        $stmt->execute($parametros);

        return $stmt->fetchAll();
    }

    /**
     * Setores com nota de troca (para o filtro), respeitando revenda e Mesa.
     */
    public function setores(?string $revenda, ?int $mesa): array
    {
        $sql = 'SELECT DISTINCT COALESCE(n.setor_codigo, c.setor_codigo) AS setor ' . self::SQL_BASE;
        $parametros = ['todas' => $revenda === null ? 1 : 0, 'revenda' => (string) $revenda];

        if ($mesa !== null) {
            $sql .= ' AND s.mesa = :mesa';
            $parametros['mesa'] = $mesa;
        }

        $stmt = $this->db->prepare($sql . ' ORDER BY setor');
        $stmt->execute($parametros);

        return array_values(array_filter(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN))));
    }
}
