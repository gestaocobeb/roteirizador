<?php

namespace App\Core\Middleware;

/**
 * Valida o token CSRF em requisições POST/PUT/PATCH/DELETE.
 *
 * Requisito atendido (Manual de Segurança, Seção 6.3):
 * toda requisição que altera estado deve conter um token
 * CSRF válido; caso contrário, a requisição é rejeitada
 * antes de qualquer processamento pelo Controller.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        if (!$token || !csrf_verify($token)) {
            http_response_code(419);
            require dirname(__DIR__, 3) . '/app/Views/errors/419.php';
            return false;
        }

        return true;
    }
}
