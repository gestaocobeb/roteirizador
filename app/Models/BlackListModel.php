<?php

namespace App\Models;

/**
 * Clientes em Black List (relatório Promax 03.02.24).
 */
class BlackListModel extends ImportavelModel
{
    protected string $table = 'clientes_black_list';

    protected array $colunasImportacao = ['cliente_codigo', 'ocorrencias'];
}
