<?php

namespace App\Models;

/**
 * Clientes classificados como Top Line.
 */
class TopLineModel extends ImportavelModel
{
    protected string $table = 'clientes_top_line';

    protected array $colunasImportacao = ['cliente_codigo', 'nome', 'observacao'];
}
