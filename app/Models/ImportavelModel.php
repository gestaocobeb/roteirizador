<?php

namespace App\Models;

use App\Core\Model;

/**
 * Base dos Models alimentados por importação de planilha.
 * Grava lotes de linhas com um único prepared statement reutilizado,
 * dentro de transação (tudo ou nada). Cada revenda tem seus próprios dados.
 */
abstract class ImportavelModel extends Model
{
    /** Colunas preenchidas a partir de cada registro importado. */
    protected array $colunasImportacao = [];

    /**
     * Apaga os registros da revenda e grava os novos (as demais revendas não mudam).
     */
    public function substituirRevenda(string $revenda, array $registros): int
    {
        $this->db->beginTransaction();

        try {
            $this->limparRevenda($revenda);
            $total = $this->inserirLote($registros, ['revenda' => $revenda]);
            $this->db->commit();
            return $total;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Apaga os registros da revenda (exclusão da importação vigente).
     *
     * @return int Quantidade de registros apagados
     */
    public function limparRevenda(string $revenda): int
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE revenda = :revenda");
        $stmt->execute(['revenda' => $revenda]);

        return $stmt->rowCount();
    }

    /**
     * @param array $fixos Valores iguais para todas as linhas (coluna => valor)
     */
    protected function inserirLote(array $registros, array $fixos = []): int
    {
        $colunas = array_merge(array_keys($fixos), $this->colunasImportacao);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $colunas),
            implode(', ', array_map(fn ($c) => ':' . $c, $colunas))
        );

        $stmt = $this->db->prepare($sql);
        $total = 0;

        foreach ($registros as $registro) {
            $parametros = $fixos;
            foreach ($this->colunasImportacao as $coluna) {
                $parametros[$coluna] = $registro[$coluna] ?? null;
            }
            $stmt->execute($parametros);
            $total++;
        }

        return $total;
    }
}
