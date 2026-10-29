<?php

/**
 * Configuração geral da aplicação.
 * Valores sensíveis vêm do .env (nunca versionar o .env real — apenas .env.example).
 */

$envFile = dirname(__DIR__) . '/.env';

if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove aspas envolventes (ex.: APP_NAME="Sistema COBEB").
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        if (!array_key_exists($key, $_ENV)) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

// Este arquivo é carregado mais de uma vez por requisição (index.php, Session,
// Database, config()); a guarda evita o erro fatal "Cannot redeclare env()".
if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);
        return $value === false ? $default : $value;
    }
}

return [
    'app' => [
        'name' => env('APP_NAME', 'Sistema COBEB'),
        'env' => env('APP_ENV', 'production'),
        'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
        'https' => filter_var(env('APP_HTTPS', 'false'), FILTER_VALIDATE_BOOLEAN),
        'base_path' => env('APP_BASE_PATH', ''),
        'session_name' => env('APP_SESSION_NAME', 'cobeb_session'),
        'session_idle_timeout' => (int) env('APP_SESSION_IDLE_TIMEOUT', 1800),
    ],
    'database' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'name' => env('DB_DATABASE', ''),
        'user' => env('DB_USERNAME', ''),
        'pass' => env('DB_PASSWORD', ''),
    ],
];
