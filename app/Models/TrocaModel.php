<?php

namespace App\Models;

/**
 * Solicitações de troca/reposição do Promax (relatório 03.18.05).
 */
class TrocaModel extends ImportavelModel
{
    protected string $table = 'trocas';

    protected array $colunasImportacao = [
        'cliente_codigo', 'cliente_nome', 'solicitacao', 'data_solicitacao', 'status_solicitacao',
        'nota_fiscal', 'produto_codigo', 'produto_descricao', 'quantidade', 'unidade',
        'sistema_origem', 'observacao', 'setor_codigo', 'valor', 'motorista_nome',
        'ajudante1_nome', 'ajudante2_nome',
    ];

    /**
     * Valor (R$) das trocas da revenda por setor, status e sistema de origem
     * ([setor][status][sistema] => valor, chaves em minúsculas). Base da Verba de troca.
     */
    public function valoresPorSetor(string $revenda): array
    {
        $stmt = $this->db->prepare(
            'SELECT setor_codigo, LOWER(status_solicitacao) AS status, LOWER(sistema_origem) AS sistema, SUM(valor) AS valor
             FROM trocas
             WHERE revenda = :revenda AND setor_codigo IS NOT NULL
             GROUP BY setor_codigo, LOWER(status_solicitacao), LOWER(sistema_origem)'
        );
        $stmt->execute(['revenda' => $revenda]);

        $resultado = [];
        foreach ($stmt->fetchAll() as $linha) {
            $resultado[(int) $linha['setor_codigo']][(string) $linha['status']][(string) $linha['sistema']] = (float) $linha['valor'];
        }

        return $resultado;
    }

    /**
     * Solicitações da revenda (uma linha por solicitação, itens somados), da mais
     * antiga para a mais nova, com a justificativa da Logística. Base da lista de
     * solicitações da Aprovação troca.
     */
    public function solicitacoes(string $revenda): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.solicitacao, t.cliente_codigo, MAX(t.cliente_nome) AS cliente_nome, MIN(t.data_solicitacao) AS data_solicitacao,
                    t.status_solicitacao, t.sistema_origem, t.setor_codigo, MAX(t.motorista_nome) AS motorista_nome,
                    MAX(t.observacao) AS observacao, SUM(t.valor) AS valor, COUNT(*) AS itens,
                    GROUP_CONCAT(CONCAT(ROUND(t.quantidade), ' ', COALESCE(t.unidade, ''), ' ', COALESCE(t.produto_descricao, ''))
                                 ORDER BY t.id SEPARATOR ' · ') AS produtos,
                    MAX(j.justificativa) AS justificativa, MAX(u.nome) AS justificativa_por, MAX(j.atualizado_em) AS justificativa_em
             FROM trocas t
             LEFT JOIN trocas_justificativas j
                    ON j.revenda = t.revenda AND j.solicitacao = t.solicitacao AND j.cliente_codigo = t.cliente_codigo
             LEFT JOIN usuarios u ON u.id = j.usuarios_id
             WHERE t.revenda = :revenda
             GROUP BY t.solicitacao, t.cliente_codigo, t.status_solicitacao, t.sistema_origem, t.setor_codigo
             ORDER BY MIN(t.data_solicitacao), CAST(t.solicitacao AS UNSIGNED), t.cliente_codigo"
        );
        $stmt->execute(['revenda' => $revenda]);

        return $stmt->fetchAll();
    }

    /**
     * A solicitação existe no 03.18.05 importado da revenda?
     */
    public function existeSolicitacao(string $revenda, string $solicitacao, int $cliente): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM trocas WHERE revenda = :revenda AND solicitacao = :solicitacao AND cliente_codigo = :cliente LIMIT 1'
        );
        $stmt->execute(['revenda' => $revenda, 'solicitacao' => $solicitacao, 'cliente' => $cliente]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Trocas aprovadas de um sistema de origem somadas por motorista (nome => valor),
     * do maior valor para o menor.
     */
    public function aprovadasPorMotorista(string $revenda, string $sistema): array
    {
        $stmt = $this->db->prepare(
            "SELECT motorista_nome AS nome, SUM(valor) AS valor
             FROM trocas
             WHERE revenda = :revenda AND status_solicitacao = 'Aprovada' AND sistema_origem = :sistema
               AND motorista_nome IS NOT NULL
             GROUP BY motorista_nome
             ORDER BY valor DESC, motorista_nome"
        );
        $stmt->execute(['revenda' => $revenda, 'sistema' => $sistema]);

        return array_map('floatval', $stmt->fetchAll(\PDO::FETCH_KEY_PAIR));
    }

    /**
     * Trocas aprovadas de um sistema de origem somadas por ajudante (nome => valor),
     * do maior valor para o menor.
     * Cada item conta para os dois ajudantes da entrega, como na planilha.
     */
    public function aprovadasPorAjudante(string $revenda, string $sistema): array
    {
        $stmt = $this->db->prepare(
            "SELECT nome, SUM(valor) AS valor FROM (
                SELECT ajudante1_nome AS nome, valor FROM trocas
                WHERE revenda = :revenda1 AND status_solicitacao = 'Aprovada' AND sistema_origem = :sistema1
                UNION ALL
                SELECT ajudante2_nome AS nome, valor FROM trocas
                WHERE revenda = :revenda2 AND status_solicitacao = 'Aprovada' AND sistema_origem = :sistema2
             ) a
             WHERE nome IS NOT NULL
             GROUP BY nome
             ORDER BY valor DESC, nome"
        );
        $stmt->execute(['revenda1' => $revenda, 'sistema1' => $sistema, 'revenda2' => $revenda, 'sistema2' => $sistema]);

        return array_map('floatval', $stmt->fetchAll(\PDO::FETCH_KEY_PAIR));
    }

    /** Setor da troca: o do relatório ou, na falta, o do cadastro 01.20.11. */
    private const SQL_ORIGEM = 'FROM trocas t
                LEFT JOIN clientes c ON c.revenda = t.revenda AND c.codigo = t.cliente_codigo
                LEFT JOIN setores s ON s.codigo = COALESCE(t.setor_codigo, c.setor_codigo)
                LEFT JOIN trocas_justificativas j
                       ON j.revenda = t.revenda AND j.solicitacao = t.solicitacao AND j.cliente_codigo = t.cliente_codigo';

    /**
     * Itens de troca filtrados (cliente, setor, status e período da Data Solicitação
     * são opcionais), mais recentes primeiro. Com $mesa, só os de setores da Mesa.
     */
    public function buscar(?int $cliente, ?int $setor, ?string $status, ?string $dataInicio, ?string $dataFim, ?string $revenda, ?int $mesa, int $limite): array
    {
        [$where, $parametros] = $this->filtros($revenda, $mesa);

        if ($cliente !== null) {
            $where .= ' AND t.cliente_codigo = :cliente';
            $parametros['cliente'] = $cliente;
        }

        if ($setor !== null) {
            $where .= ' AND COALESCE(t.setor_codigo, c.setor_codigo) = :setor';
            $parametros['setor'] = $setor;
        }

        if ($status !== null) {
            $where .= ' AND t.status_solicitacao = :status';
            $parametros['status'] = $status;
        }

        if ($dataInicio !== null) {
            $where .= ' AND t.data_solicitacao >= :data_inicio';
            $parametros['data_inicio'] = $dataInicio;
        }

        if ($dataFim !== null) {
            $where .= ' AND t.data_solicitacao <= :data_fim';
            $parametros['data_fim'] = $dataFim;
        }

        $stmt = $this->db->prepare(
            'SELECT t.*, COALESCE(t.setor_codigo, c.setor_codigo) AS setor, s.mesa, j.justificativa ' . self::SQL_ORIGEM . $where
            . ' ORDER BY t.revenda, t.data_solicitacao DESC, CAST(t.solicitacao AS UNSIGNED) DESC, t.id LIMIT :limite'
        );
        foreach ($parametros as $chave => $valor) {
            $stmt->bindValue(':' . $chave, $valor, is_int($valor) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Setores e status existentes nas trocas visíveis (para os filtros da tela).
     *
     * @return array{setores: int[], status: string[]}
     */
    public function opcoesFiltro(?string $revenda, ?int $mesa): array
    {
        [$where, $parametros] = $this->filtros($revenda, $mesa);

        $stmt = $this->db->prepare('SELECT DISTINCT s.codigo ' . self::SQL_ORIGEM . $where . ' AND s.codigo IS NOT NULL ORDER BY s.codigo');
        $stmt->execute($parametros);
        $setores = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        $stmt = $this->db->prepare('SELECT DISTINCT t.status_solicitacao ' . self::SQL_ORIGEM . $where . " AND t.status_solicitacao <> '' ORDER BY t.status_solicitacao");
        $stmt->execute($parametros);

        return ['setores' => $setores, 'status' => $stmt->fetchAll(\PDO::FETCH_COLUMN)];
    }

    /**
     * Restrição de revenda e Mesa comum às consultas da aba Troca.
     */
    private function filtros(?string $revenda, ?int $mesa): array
    {
        $where = ' WHERE 1 = 1';
        $parametros = [];

        if ($revenda !== null) {
            $where .= ' AND t.revenda = :revenda';
            $parametros['revenda'] = $revenda;
        }

        if ($mesa !== null) {
            $where .= ' AND s.mesa = :mesa';
            $parametros['mesa'] = $mesa;
        }

        return [$where, $parametros];
    }
}
