<?php

namespace App\Core;

/**
 * Router
 *
 * Roteador simples e explícito do Framework COBEB.
 * Rotas são definidas em config/routes.php e resolvidas
 * a partir do path relativo ao base path do sistema
 * (necessário para compatibilidade com o Proxy Reverso do
 * Portal Corporativo — ver Manual de Desenvolvimento, Seção 9).
 */
class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
    ];

    private string $basePath;

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function get(string $path, $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, $handler, array $middlewares = []): void
    {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, $handler, array $middlewares = []): void
    {
        $this->add('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $path, $handler, $middlewares);
    }

    private function add(string $method, string $path, $handler, array $middlewares): void
    {
        $pattern = $this->toRegex($path);
        $this->routes[$method][] = compact('pattern', 'handler', 'middlewares', 'path');
    }

    private function toRegex(string $path): string
    {
        // Converte {param} em grupo nomeado de captura.
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $regex . '$#';
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        $path = substr($uri, strlen($this->basePath));
        $path = '/' . ltrim($path === false ? '' : $path, '/');
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                foreach ($route['middlewares'] as $middlewareClass) {
                    $middleware = new $middlewareClass();
                    if ($middleware->handle() === false) {
                        return;
                    }
                }

                $this->call($route['handler'], $params);
                return;
            }
        }

        $this->notFound();
    }

    private function call($handler, array $params): void
    {
        if (is_array($handler)) {
            [$controllerClass, $action] = $handler;
            $controller = new $controllerClass();
            call_user_func_array([$controller, $action], $params);
            return;
        }

        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }

        throw new \RuntimeException('Handler de rota inválido.');
    }

    private function notFound(): void
    {
        http_response_code(404);
        require dirname(__DIR__) . '/Views/errors/404.php';
    }
}
