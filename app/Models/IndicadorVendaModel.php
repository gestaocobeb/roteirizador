<?php

namespace App\Models;

/**
 * Indicadores de vendas do Promax 17.03 (coluna "Valor" de cada "Cod. ID").
 */
class IndicadorVendaModel extends ImportavelModel
{
    protected string $table = 'indicadores_vendas';

    protected array $colunasImportacao = ['codigo', 'descricao', 'valor'];

    /**
     * Valor do indicador na revenda, ou null quando o 17.03 não o trouxe.
     */
    public function valor(string $revenda, int $codigo): ?float
    {
        $stmt = $this->db->prepare('SELECT valor FROM indicadores_vendas WHERE revenda = :revenda AND codigo = :codigo LIMIT 1');
        $stmt->execute(['revenda' => $revenda, 'codigo' => $codigo]);
        $valor = $stmt->fetchColumn();

        return $valor === false ? null : (float) $valor;
    }
}
