<?php

namespace App\Models;

/**
 * Clientes com trava de entrega (horário, recarga, descarga etc.).
 */
class TravaModel extends ImportavelModel
{
    protected string $table = 'clientes_travas';

    protected array $colunasImportacao = ['cliente_codigo', 'nome', 'setor_codigo', 'condicao_entrega', 'motivo'];
}
