<?php

namespace App\Core;

/**
 * Auth
 *
 * Autenticação padrão do Framework COBEB.
 *
 * Requisitos atendidos (Manual de Segurança, Seção 6.5 e 6.7):
 * - Senhas nunca em texto puro; uso de password_hash()/password_verify().
 * - MD5/SHA1 proibidos para senhas.
 * - Regeneração do ID de sessão após login bem-sucedido.
 *
 * Esta classe assume uma tabela `usuarios` com colunas `id`, `email`
 * e `senha` (hash). Projetos podem estender/adaptar conforme o domínio,
 * mantendo o mesmo contrato de segurança.
 */
class Auth
{
    private const SESSION_KEY = 'auth_user_id';

    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::getInstance();

        $stmt = $pdo->prepare('SELECT id, senha FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($password, $usuario['senha'])) {
            Logger::warning("Tentativa de login inválida para: {$email}");
            return false;
        }

        // Regenera o ID de sessão para evitar Session Fixation.
        Session::regenerate();
        Session::set(self::SESSION_KEY, $usuario['id']);

        return true;
    }

    public static function check(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public static function id()
    {
        return Session::get(self::SESSION_KEY);
    }

    public static function logout(): void
    {
        Session::remove(self::SESSION_KEY);
        Session::destroy();
    }

    /**
     * Gera um hash seguro de senha. Deve ser usado em qualquer
     * cadastro ou alteração de senha em toda a plataforma CDP.
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}
