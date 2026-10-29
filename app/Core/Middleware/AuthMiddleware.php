<?php

namespace App\Core\Middleware;

use App\Core\Auth;

/**
 * Bloqueia o acesso a rotas que exigem usuário autenticado,
 * redirecionando para a tela de login quando necessário.
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (!Auth::check()) {
            header('Location: /login');
            return false;
        }

        return true;
    }
}
