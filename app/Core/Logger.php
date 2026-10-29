<?php

namespace App\Core;

/**
 * Logger
 *
 * Registro simples de eventos e erros em arquivo, fora do webroot.
 * Requisito atendido (Manual de Segurança, Seção 6.8): erros nunca
 * devem ser exibidos ao usuário final em produção, apenas logados.
 */
class Logger
{
    private static function path(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';

        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        return $dir . '/app-' . date('Y-m-d') . '.log';
    }

    private static function write(string $level, string $message): void
    {
        $line = sprintf('[%s] %s: %s%s', date('Y-m-d H:i:s'), $level, $message, PHP_EOL);
        file_put_contents(self::path(), $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    public static function warning(string $message): void
    {
        self::write('WARNING', $message);
    }

    public static function error(string $message): void
    {
        self::write('ERROR', $message);
    }
}
