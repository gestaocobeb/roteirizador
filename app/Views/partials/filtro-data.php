<?php
/**
 * Seletor de data de entrega (e de Mesa, quando $mesas for informado; e de
 * revenda, quando $filtroRevenda for true e o usuário não estiver preso a uma).
 * Variáveis: $acao (rota), $dataRef, opcionais $mesas, $mesa, $permiteTodas, $perfil, $filtroRevenda.
 */
$mesas = $mesas ?? null;
?>
<form method="get" action="<?= e(base_url($acao)) ?>" class="row g-2 align-items-end d-print-none">
    <div class="col-auto">
        <label for="filtroData" class="form-label small mb-1">Data de entrega</label>
        <input type="date" class="form-control form-control-sm" id="filtroData" name="data" value="<?= e($dataRef) ?>" data-autosubmit>
    </div>
    <?php if (!empty($filtroRevenda) && empty($revendaFixa)): ?>
        <div class="col-auto">
            <label for="filtroRevenda" class="form-label small mb-1">Revenda</label>
            <select class="form-select form-select-sm" id="filtroRevenda" name="revenda" data-autosubmit data-limpa="mesa">
                <option value="">Todas</option>
                <?php foreach ($revendasMenu as $sigla): ?>
                    <option value="<?= e($sigla) ?>" <?= $sigla === $revendaAtual ? 'selected' : '' ?>><?= e($sigla) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <?php if ($mesas !== null && ($perfil ?? '') !== 'comercial'): ?>
        <div class="col-auto">
            <label for="filtroMesa" class="form-label small mb-1">Sala</label>
            <select class="form-select form-select-sm" id="filtroMesa" name="mesa" data-autosubmit>
                <?php if (!empty($permiteTodas)): ?>
                    <option value="">Todas</option>
                <?php endif; ?>
                <?php foreach ($mesas as $m): ?>
                    <option value="<?= e((string) $m) ?>" <?= $m === $mesa ? 'selected' : '' ?>>Sala <?= e((string) $m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <noscript><div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-secondary">Atualizar</button></div></noscript>
</form>
