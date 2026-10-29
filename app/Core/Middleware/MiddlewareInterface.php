<?php

namespace App\Core\Middleware;

/**
 * Contrato que todo Middleware do Framework COBEB deve implementar.
 * Retornar `false` interrompe o fluxo da requisição.
 */
interface MiddlewareInterface
{
    public function handle(): bool;
}
