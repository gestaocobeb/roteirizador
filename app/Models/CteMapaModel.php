<?php

namespace App\Models;

use App\Helpers\Cte;

/**
 * Mapas do CTE por revenda (todos do 03.11.49.02; a planilha mostra os com motorista).
 */
class CteMapaModel extends ImportavelModel
{
    protected string $table = 'cte_mapas';

    protected array $colunasImportacao = [
        'data_entrega', 'numero_mapa', 'placa', 'motorista_promax', 'entregas', 'peso', 'cidades',
        'valor', 'cte_numero', 'motorista_nome', 'motorista_codigo',
    ];

    /** Campos digitados na tela. */
    public const DIGITADOS = ['cte_numero', 'motorista_nome', 'motorista_codigo'];

    /**
     * Linhas da planilha: só os mapas com motorista (colado em Motoristas), sem as
     * placas ignoradas (Cte::PLACAS_IGNORADAS), ordenadas pelo mapa (como a macro
     * "Classificar" da planilha).
     */
    public function daRevenda(string $revenda): array
    {
        $ignoradas = implode(', ', array_fill(0, count(Cte::PLACAS_IGNORADAS), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM cte_mapas
             WHERE revenda = ? AND placa NOT IN ({$ignoradas})
               AND (motorista_nome IS NOT NULL OR motorista_codigo IS NOT NULL)
             ORDER BY CAST(numero_mapa AS UNSIGNED), placa"
        );
        $stmt->execute([$revenda, ...Cte::PLACAS_IGNORADAS]);

        return $stmt->fetchAll();
    }

    /**
     * Quantos mapas do 03.11.49.02 da revenda ainda não têm motorista colado.
     */
    public function semMotorista(string $revenda): int
    {
        $ignoradas = implode(', ', array_fill(0, count(Cte::PLACAS_IGNORADAS), '?'));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM cte_mapas
             WHERE revenda = ? AND placa NOT IN ({$ignoradas})
               AND motorista_nome IS NULL AND motorista_codigo IS NULL"
        );
        $stmt->execute([$revenda, ...Cte::PLACAS_IGNORADAS]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Todos os mapas guardados da revenda (inclusive os que não estão na planilha).
     */
    private function todos(string $revenda): array
    {
        $stmt = $this->db->prepare('SELECT * FROM cte_mapas WHERE revenda = :revenda');
        $stmt->execute(['revenda' => $revenda]);

        return $stmt->fetchAll();
    }

    /**
     * Substitui os mapas da revenda pelos do 03.11.49.02 enviado, mantendo o valor
     * e o que já foi digitado nos mapas que continuam no arquivo.
     */
    public function importar(string $revenda, array $registros): int
    {
        $anteriores = [];
        foreach ($this->todos($revenda) as $linha) {
            $anteriores[$linha['numero_mapa']] = $linha;
        }

        foreach ($registros as &$r) {
            $antes = $anteriores[$r['numero_mapa']] ?? [];
            $r['valor'] = $antes['valor'] ?? null;
            foreach (self::DIGITADOS as $campo) {
                $r[$campo] = $antes[$campo] ?? null;
            }
        }
        unset($r);

        return $this->substituirRevenda($revenda, $registros);
    }

    /**
     * Grava o valor do 03.03.12 nos mapas da revenda. Devolve quantos mapas foram atualizados.
     *
     * @param array<string, float> $valores mapa => valor
     */
    public function gravarValores(string $revenda, array $valores): int
    {
        // Só os mapas que estão no CTE (rowCount não conta linha com o mesmo valor).
        $existentes = array_flip(array_column($this->todos($revenda), 'numero_mapa'));
        $valores = array_intersect_key($valores, $existentes);
        $stmt = $this->db->prepare('UPDATE cte_mapas SET valor = :valor WHERE revenda = :revenda AND numero_mapa = :mapa');

        foreach ($valores as $mapa => $valor) {
            $stmt->execute(['valor' => $valor, 'revenda' => $revenda, 'mapa' => (string) $mapa]);
        }

        return count($valores);
    }

    /**
     * Preenche motorista e código pelos dados colados (só o que veio preenchido).
     * Devolve quantos mapas do CTE foram preenchidos.
     *
     * @param array<string, array{codigo: ?string, nome: ?string}> $motoristas mapa => dados
     */
    public function gravarMotoristas(string $revenda, array $motoristas): int
    {
        $existentes = array_flip(array_column($this->todos($revenda), 'numero_mapa'));
        $motoristas = array_intersect_key($motoristas, $existentes);
        $stmt = $this->db->prepare(
            'UPDATE cte_mapas SET motorista_nome = COALESCE(:nome, motorista_nome), motorista_codigo = COALESCE(:codigo, motorista_codigo)
             WHERE revenda = :revenda AND numero_mapa = :mapa'
        );

        foreach ($motoristas as $mapa => $m) {
            $stmt->execute(['nome' => $m['nome'], 'codigo' => $m['codigo'], 'revenda' => $revenda, 'mapa' => (string) $mapa]);
        }

        return count($motoristas);
    }

    /**
     * Grava os campos digitados (Ct'e, motorista e código) das linhas da revenda.
     *
     * @param array<int, array> $linhas id => [campo => valor]
     */
    public function gravarDigitados(string $revenda, array $linhas): void
    {
        $stmt = $this->db->prepare(
            'UPDATE cte_mapas SET cte_numero = :cte, motorista_nome = :nome, motorista_codigo = :codigo
             WHERE id = :id AND revenda = :revenda'
        );

        $this->db->beginTransaction();
        try {
            foreach ($linhas as $id => $l) {
                $stmt->execute([
                    'cte' => $l['cte_numero'],
                    'nome' => $l['motorista_nome'],
                    'codigo' => $l['motorista_codigo'],
                    'id' => (int) $id,
                    'revenda' => $revenda,
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
