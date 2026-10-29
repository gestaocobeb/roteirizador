<?php
// Menu: somente as abas liberadas ao usuário (calculadas no BaseController).
$abasMenu = $abasMenu ?? [];
$rotaAtual = $rotaAtual ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(config('app.name')) ?> &middot; COBEB</title>
    <link rel="icon" type="image/png" href="<?= e(asset('img/logo-cobeb.png')) ?>">
    <!-- Celular: cor da barra do navegador e ícone ao adicionar à tela inicial -->
    <meta name="theme-color" content="#0a4fa0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Roteirizador">
    <link rel="apple-touch-icon" href="<?= e(asset('img/icone-app.png')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="<?= empty($usuarioLogado) ? 'pagina-acesso' : '' ?>">

<?php if (!empty($usuarioLogado)): ?>
    <?php
    // Iniciais do usuário para o avatar do cabeçalho (apresentação).
    $partesNome = preg_split('/\s+/', trim((string) $usuarioLogado['nome'])) ?: [''];
    $iniciais = mb_strtoupper(mb_substr($partesNome[0], 0, 1) . (count($partesNome) > 1 ? mb_substr(end($partesNome), 0, 1) : ''));
    $rotuloPerfil = \App\Models\UsuarioModel::PERFIS[$usuarioLogado['perfil']] ?? $usuarioLogado['perfil'];
    ?>
    <header class="topo-cobeb d-print-none">
        <!-- Faixa da marca: logo, sistema, revenda e usuário -->
        <div class="barra-marca">
            <div class="container-fluid px-lg-4 d-flex flex-wrap align-items-center gap-2 gap-md-3">
                <a class="d-flex align-items-center gap-3 text-decoration-none me-auto" href="<?= e(base_url()) ?>">
                    <img src="<?= e(asset('img/logo-cobeb.png')) ?>" alt="COBEB Distribuidora" class="logo-navbar">
                    <span class="nome-sistema d-none d-sm-flex flex-column">
                        <span><?= e(config('app.name')) ?></span>
                        <small>Logística &middot; Comercial</small>
                    </span>
                </a>

                <!-- Seletores de Sala/Revenda: na mesma linha no computador, numa segunda linha no celular -->
                <div class="seletores-topo d-flex align-items-center gap-2">
                <?php if (count($salasMenu ?? []) > 1): ?>
                    <!-- Comercial com mais de uma Sala: Sala em uso (guardada na sessão). -->
                    <form method="post" action="<?= e(base_url('sala')) ?>" class="seletor-revenda-topo d-flex align-items-center gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="voltar" value="<?= e($rotaAtual) ?>">
                        <label for="seletorSala" class="mb-0">Sala</label>
                        <select class="form-select form-select-sm seletor-revenda" id="seletorSala" name="sala" data-autosubmit>
                            <?php foreach ($salasMenu as $sala): ?>
                                <option value="<?= e((string) $sala) ?>" <?= $sala === ($salaAtual ?? null) ? 'selected' : '' ?>><?= e((string) $sala) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">OK</button></noscript>
                    </form>
                <?php endif; ?>

                <?php if (!empty($revendaFixa)): ?>
                    <span class="badge badge-revenda fs-6" title="Sua revenda">Revenda <?= e((string) ($revendaAtual ?: '—')) ?></span>
                <?php else: ?>
                    <!-- Revenda em uso nas telas (guardada na sessão). -->
                    <form method="post" action="<?= e(base_url('revenda')) ?>" class="seletor-revenda-topo d-flex align-items-center gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="voltar" value="<?= e($rotaAtual) ?>">
                        <label for="seletorRevenda" class="mb-0">Revenda</label>
                        <select class="form-select form-select-sm seletor-revenda" id="seletorRevenda" name="revenda" data-autosubmit>
                            <option value="">Todas</option>
                            <?php foreach ($revendasMenu ?? [] as $sigla): ?>
                                <option value="<?= e($sigla) ?>" <?= $sigla === ($revendaAtual ?? null) ? 'selected' : '' ?>><?= e($sigla) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">OK</button></noscript>
                    </form>
                <?php endif; ?>
                </div>

                <div class="usuario-topo d-flex align-items-center gap-2">
                    <span class="avatar-usuario" aria-hidden="true"><?= e($iniciais) ?></span>
                    <span class="d-none d-md-flex flex-column lh-sm">
                        <span class="fw-semibold"><?= e($usuarioLogado['nome']) ?></span>
                        <small>
                            <?= e($rotuloPerfil) ?>
                            <?php if ($usuarioLogado['perfil'] === 'comercial'): ?>
                                &middot; <span class="badge badge-mesa">Sala <?= e((string) ($salaAtual ?: '—')) ?></span>
                            <?php endif; ?>
                        </small>
                    </span>
                </div>

                <form method="post" action="<?= e(base_url('logout')) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-sair" title="Sair do sistema"><i class="bi bi-box-arrow-right"></i><span class="d-none d-lg-inline"> Sair</span></button>
                </form>
            </div>
        </div>

        <!-- Faixa do menu: abas liberadas ao usuário -->
        <nav class="navbar navbar-expand-lg navbar-dark barra-menu py-0" aria-label="Menu principal">
            <div class="container-fluid px-lg-4">
                <button class="navbar-toggler my-2 d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir menu">
                    <span class="navbar-toggler-icon"></span> <span class="small text-white">Menu</span>
                </button>
                <div class="collapse navbar-collapse" id="menuPrincipal">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link <?= $rotaAtual === '' ? 'active' : '' ?>" href="<?= e(base_url()) ?>"><i class="bi bi-house-door"></i> Painel</a>
                        </li>
                        <?php foreach ($abasMenu as $rota => $aba): ?>
                            <li class="nav-item">
                                <a class="nav-link <?= $rotaAtual === $rota ? 'active' : '' ?>" <?= $rotaAtual === $rota ? 'aria-current="page"' : '' ?> href="<?= e(base_url($rota)) ?>">
                                    <i class="bi <?= e($aba['icone']) ?>"></i> <?= e($aba['rotulo']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
<?php endif; ?>

<main class="<?= empty($usuarioLogado) ? '' : 'conteudo-cobeb ' ?>container-fluid py-4 px-lg-4">
    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= e($flash['tipo']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['mensagem']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <?php require $viewFile; ?>
</main>

<footer class="<?= empty($usuarioLogado) ? 'text-center py-4' : 'rodape-cobeb' ?> small d-print-none">
    <?php if (empty($usuarioLogado)): ?>
        COBEB Distribuidora &middot; Roteirizador
    <?php else: ?>
        <div class="container-fluid px-lg-4 d-flex flex-wrap justify-content-between gap-2">
            <span>&copy; <?= e(date('Y')) ?> COBEB Distribuidora &middot; <?= e(config('app.name')) ?></span>
            <span>COBEB Development Platform (CDP)</span>
        </div>
    <?php endif; ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
