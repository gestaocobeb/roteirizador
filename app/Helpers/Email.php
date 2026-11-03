<?php

namespace App\Helpers;

use App\Core\Logger;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envio de e-mail via SMTP (PHPMailer), com as credenciais de config('mail').
 * Falhas nunca chegam ao usuário: são registradas no Logger e o método devolve false.
 */
class Email
{
    /**
     * @param string $view  Template em app/Views, ex.: "emails/redefinir-senha"
     * @param array  $dados Variáveis disponibilizadas ao template
     */
    public static function enviar(string $para, string $nome, string $assunto, string $view, array $dados = []): bool
    {
        $mail = config('mail');

        if (!class_exists(PHPMailer::class) || $mail['host'] === '' || $mail['from_address'] === '') {
            Logger::error('E-mail não enviado: PHPMailer ausente (composer install) ou SMTP não configurado no .env.');
            return false;
        }

        $phpMailer = new PHPMailer(true);

        try {
            $phpMailer->isSMTP();
            $phpMailer->Host = $mail['host'];
            $phpMailer->Port = $mail['port'];
            $phpMailer->CharSet = PHPMailer::CHARSET_UTF8;

            if ($mail['encryption'] === 'ssl') {
                $phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($mail['encryption'] === 'tls') {
                $phpMailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $phpMailer->SMTPAutoTLS = false;
            }

            if ($mail['user'] !== '') {
                $phpMailer->SMTPAuth = true;
                $phpMailer->Username = $mail['user'];
                $phpMailer->Password = $mail['pass'];
            }

            $phpMailer->setFrom($mail['from_address'], $mail['from_name']);
            $phpMailer->addAddress($para, $nome);
            $phpMailer->Subject = $assunto;
            $phpMailer->isHTML(true);
            $phpMailer->Body = self::renderizar($view, $dados);
            $phpMailer->AltBody = trim(html_entity_decode(strip_tags($phpMailer->Body), ENT_QUOTES, 'UTF-8'));

            $phpMailer->send();
            return true;
        } catch (PHPMailerException $e) {
            Logger::error("Falha ao enviar e-mail para {$para}: {$phpMailer->ErrorInfo}");
            return false;
        }
    }

    private static function renderizar(string $view, array $dados): string
    {
        extract($dados, EXTR_SKIP);

        ob_start();
        require dirname(__DIR__) . "/Views/{$view}.php";

        return (string) ob_get_clean();
    }
}
