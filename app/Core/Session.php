<?php

namespace App\Core;

/**
 * Session
 *
 * Encapsula o gerenciamento de sessão da aplicação.
 *
 * Requisitos atendidos (Manual de Segurança, Seção 6.7):
 * - Cookies HttpOnly, SameSite e Secure (quando HTTPS).
 * - Regeneração de ID após login (ver Auth::attempt).
 * - Expiração por inatividade configurável.
 */
class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $secure = ($config['app']['https'] ?? false) === true;

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name($config['app']['session_name'] ?? 'cobeb_session');
        session_start();

        self::enforceIdleTimeout($config['app']['session_idle_timeout'] ?? 1800);
    }

    private static function enforceIdleTimeout(int $seconds): void
    {
        if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity']) > $seconds) {
            self::destroy();
            self::start();
        }

        $_SESSION['_last_activity'] = time();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }
}
