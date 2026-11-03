<?php

/**
 * Configuração geral da aplicação.
 * Valores sensíveis vêm do .env (nunca versionar o .env real — apenas .env.example).
 */

$envFile = dirname(__DIR__) . '/.env';

if (is_file($envFile)) {
    // Nomes $env* de propósito: config() (helpers.php) faz require deste arquivo dentro da
    // função, no mesmo escopo; $key/$value sobrescreviam o parâmetro $key de config().
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envLine) {
        if (str_starts_with(trim($envLine), '#') || !str_contains($envLine, '=')) {
            continue;
        }
        [$envKey, $envValue] = explode('=', $envLine, 2);
        $envKey = trim($envKey);
        $envValue = trim($envValue);
        // Remove aspas envolventes (ex.: APP_NAME="Sistema COBEB").
        if (strlen($envValue) >= 2 && ($envValue[0] === '"' || $envValue[0] === "'") && substr($envValue, -1) === $envValue[0]) {
            $envValue = substr($envValue, 1, -1);
        }
        if (!array_key_exists($envKey, $_ENV)) {
            putenv("{$envKey}={$envValue}");
            $_ENV[$envKey] = $envValue;
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
        // Endereço público completo (ex.: https://portal.cobeb.com.br/roteirizador), usado nos
        // links enviados por e-mail. Nunca derivar do Host da requisição (Host header poisoning).
        'url' => env('APP_URL', ''),
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
    'mail' => [
        'host' => env('MAIL_HOST', ''),
        'port' => (int) env('MAIL_PORT', 587),
        // tls (STARTTLS, porta 587), ssl (porta 465) ou vazio (sem criptografia).
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'user' => env('MAIL_USERNAME', ''),
        'pass' => env('MAIL_PASSWORD', ''),
        'from_address' => env('MAIL_FROM_ADDRESS', ''),
        'from_name' => env('MAIL_FROM_NAME', 'Roteirizador COBEB'),
    ],
];
