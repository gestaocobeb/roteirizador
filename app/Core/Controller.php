<?php

namespace App\Core;

/**
 * Controller base.
 *
 * Todo Controller do projeto deve estender esta classe.
 * Controllers não devem conter SQL (Manual de Desenvolvimento, Seção 4.2) —
 * apenas orquestrar Models e Views.
 */
abstract class Controller
{
    /**
     * Renderiza uma View dentro do layout padrão.
     *
     * @param string $view  Caminho relativo em app/Views, ex.: "home/index"
     * @param array  $data  Dados disponibilizados para a View (chave => valor)
     */
    protected function view(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $viewFile = dirname(__DIR__) . "/Views/{$view}.php";

        if (!is_file($viewFile)) {
            throw new \RuntimeException("View não encontrada: {$view}");
        }

        require dirname(__DIR__) . '/Views/layouts/main.php';
    }

    /**
     * Redireciona o navegador para outra rota do próprio sistema.
     */
    protected function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    /**
     * Retorna uma resposta JSON, encerrando a execução.
     */
    protected function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Retorna o corpo da requisição (POST) já sem espaços supérfluos.
     * Não substitui validação específica de cada campo.
     */
    protected function input(string $key, $default = null)
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }
}
