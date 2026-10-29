<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Database
 *
 * Camada única de acesso ao banco de dados via PDO.
 * Toda a aplicação deve obter a conexão exclusivamente através desta classe.
 *
 * Requisitos atendidos (Manual de Segurança, Seção 6.1):
 * - Uso obrigatório de PDO com prepared statements.
 * - Charset utf8mb4 / collation utf8mb4_unicode_ci.
 * - Erros de conexão nunca expostos diretamente ao usuário final em produção.
 */
class Database
{
    private static ?PDO $instance = null;

    private function __construct()
    {
        // Impede instanciação direta - uso exclusivo via getInstance().
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = require dirname(__DIR__, 2) . '/config/config.php';
            $db = $config['database'];

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $db['host'],
                $db['port'],
                $db['name']
            );

            try {
                self::$instance = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                Logger::error('Falha de conexão com o banco de dados: ' . $e->getMessage());

                if (($config['app']['env'] ?? 'production') === 'production') {
                    http_response_code(500);
                    exit('Erro interno. Tente novamente mais tarde.');
                }

                throw $e;
            }
        }

        return self::$instance;
    }

    private function __clone()
    {
        // Não permitido - Singleton.
    }

    public function __wakeup()
    {
        throw new \Exception('Não é permitido desserializar um Singleton.');
    }
}
