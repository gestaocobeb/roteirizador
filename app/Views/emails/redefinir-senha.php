<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Redefinição de senha</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#212529;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:8px;padding:28px;">
        <h1 style="font-size:18px;color:#0a4fa0;margin:0 0 16px;">Roteirizador COBEB</h1>
        <p>Olá, <?= e($nome) ?>.</p>
        <p>Recebemos um pedido para redefinir a sua senha. Clique no botão abaixo para criar uma nova:</p>
        <p style="text-align:center;margin:28px 0;">
            <a href="<?= e($link) ?>" style="background:#0a4fa0;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;display:inline-block;">Criar nova senha</a>
        </p>
        <p style="font-size:13px;color:#6c757d;">
            O link vale por 1 hora e só pode ser usado uma vez. Se o botão não funcionar, copie este endereço no navegador:<br>
            <?= e($link) ?>
        </p>
        <p style="font-size:13px;color:#6c757d;">Se você não pediu a troca de senha, ignore este e-mail: a sua senha atual continua valendo.</p>
    </div>
</body>
</html>
