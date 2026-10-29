<?php

namespace App\Models;

/**
 * Cadastro de clientes (relatório Promax 01.20.11).
 */
class ClienteModel extends ImportavelModel
{
    protected string $table = 'clientes';

    protected array $colunasImportacao = [
        'codigo', 'razao_social', 'nome_fantasia', 'setor_codigo', 'cidade',
        'bairro', 'dia_visita', 'status_cliente', 'segmento',
    ];
}
