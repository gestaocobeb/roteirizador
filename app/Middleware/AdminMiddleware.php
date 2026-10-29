<?php

namespace App\Middleware;

/**
 * Gestão de usuários: somente Administrador.
 */
class AdminMiddleware extends PerfilMiddleware
{
    protected array $perfis = ['admin'];
}
