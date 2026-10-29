<?php

namespace App\Middleware;

/**
 * Importações e cadastros: Logística e Administrador.
 */
class LogisticaMiddleware extends PerfilMiddleware
{
    protected array $perfis = ['admin', 'logistica'];
}
