<?php

use App\Core\Session;

/**
 * Funções globais do Framework COBEB.
 * Disponíveis em toda a aplicação, incluindo as Views.
 */

if (!function_exists('e')) {
    /**
     * Escapa dados dinâmicos para saída segura em HTML.
     * Uso obrigatório em toda View (Manual de Segurança, Seção 6.2).
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Retorna o token CSRF da sessão atual, gerando-o se necessário.
     */
    function csrf_token(): string
    {
        if (!Session::has('_csrf_token')) {
            Session::set('_csrf_token', bin2hex(random_bytes(32)));
        }

        return Session::get('_csrf_token');
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Gera o campo hidden pronto para uso em formulários.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(string $token): bool
    {
        return Session::has('_csrf_token') && hash_equals(Session::get('_csrf_token'), $token);
    }
}

if (!function_exists('config')) {
    /**
     * Lê um valor de configuração usando notação de ponto,
     * ex.: config('app.env').
     */
    function config(string $key, $default = null)
    {
        static $config;

        if ($config === null) {
            $config = require dirname(__DIR__, 2) . '/config/config.php';
        }

        $value = $config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('asset')) {
    /**
     * Monta a URL de um asset respeitando o base path do Portal Corporativo.
     */
    function asset(string $path): string
    {
        return rtrim(config('app.base_path', ''), '/') . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        return rtrim(config('app.base_path', ''), '/') . '/' . ltrim($path, '/');
    }
}
