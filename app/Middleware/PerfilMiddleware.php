<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Middleware\MiddlewareInterface;
use App\Models\UsuarioModel;

/**
 * Base dos middlewares de perfil do Roteirizador.
 *
 * Deve ser usado depois do AuthMiddleware do Framework. Encerra a sessão de
 * usuários desativados, bloqueia (403) quem não tem um dos perfis exigidos e
 * quem não tem liberada a aba correspondente à rota (inclusive em POST).
 */
abstract class PerfilMiddleware implements MiddlewareInterface
{
    /** Perfis autorizados; vazio = qualquer usuário ativo. */
    protected array $perfis = [];

    public function handle(): bool
    {
        $model = new UsuarioModel();
        $usuario = Auth::check() ? $model->buscar(Auth::id()) : null;

        if (!$usuario || (int) $usuario['ativo'] !== 1) {
            Auth::logout();
            header('Location: ' . base_url('login'));
            return false;
        }

        if ($this->perfis !== [] && !in_array($usuario['perfil'], $this->perfis, true)) {
            return $this->negar();
        }

        $aba = self::abaDaRota();

        if ($aba !== null && !in_array($aba, $model->abasLiberadas($usuario), true)) {
            return $this->negar();
        }

        return true;
    }

    private function negar(): bool
    {
        http_response_code(403);
        require dirname(__DIR__) . '/Views/errors/403.php';
        return false;
    }

    /**
     * Aba da rota atual (primeiro segmento do caminho, sem o base path do Portal),
     * ou null quando a rota não pertence a nenhuma aba (painel, logout etc.).
     */
    public static function abaDaRota(): ?string
    {
        $caminho = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $basePath = rtrim((string) config('app.base_path', ''), '/');

        if ($basePath !== '' && str_starts_with($caminho, $basePath)) {
            $caminho = substr($caminho, strlen($basePath));
        }

        $segmento = explode('/', trim($caminho, '/'))[0];

        return array_key_exists($segmento, UsuarioModel::ABAS) ? $segmento : null;
    }
}
