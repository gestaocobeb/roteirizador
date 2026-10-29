<?php

namespace App\Models;

/**
 * Quantidade de linhas de nota por cliente (relatório Promax 03.02.37).
 * Alimenta o indicador "Já comprou?" da Crítica.
 */
class HistoricoCompraModel extends ImportavelModel
{
    protected string $table = 'historico_compras';

    protected array $colunasImportacao = ['cliente_codigo', 'quantidade'];
}
