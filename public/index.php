<?php

/**
 * Front Controller - único ponto de entrada HTTP do sistema.
 * Todo o restante da aplicação vive fora do webroot (public/).
 */

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/Helpers/helpers.php';

use App\Core\Router;
use App\Core\Session;

$config = require dirname(__DIR__) . '/config/config.php';

date_default_timezone_set($config['app']['timezone'] ?: 'America/Sao_Paulo');

// Exibição de erros controlada pelo ambiente (Manual de Segurança, Seção 6.8).
if ($config['app']['env'] === 'production') {
    ini_set('display_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Headers de segurança (Manual de Segurança, Seção 6.9).
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
if ($config['app']['https']) {
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains');
}

Session::start();

$router = new Router($config['app']['base_path']);
require dirname(__DIR__) . '/config/routes.php';
$router->dispatch();
