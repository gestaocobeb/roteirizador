<?php
/**
 * Campo "Revenda" dos formulários de importação.
 * Usuário restrito a uma revenda envia sempre a própria (validada no Controller).
 * Variáveis do layout: $revendaAtual, $revendaFixa.
 */
?>
<div class="mb-3">
    <label for="campoRevenda" class="form-label">Revenda</label>
    <?php if ($revendaFixa): ?>
        <input type="hidden" name="revenda" value="<?= e((string) $revendaAtual) ?>">
        <input type="text" class="form-control" id="campoRevenda" value="<?= e((string) $revendaAtual) ?>" readonly>
    <?php else: ?>
        <select class="form-select" id="campoRevenda" name="revenda" required>
            <option value="">Selecione...</option>
            <?php foreach ($revendasMenu as $sigla): ?>
                <option value="<?= e($sigla) ?>" <?= $sigla === $revendaAtual ? 'selected' : '' ?>><?= e($sigla) ?></option>
            <?php endforeach; ?>
            <option value="todas">Todas (separar pelo arquivo) &mdash; Cora, 03.18.05 e Farol</option>
        </select>
    <?php endif; ?>
    <div class="form-text">
        A importação substitui somente os dados desta revenda. No Cora, no 03.18.05 e nos relatórios do Farol o sistema confere a revenda pelo próprio arquivo.
        <?php if (!$revendaFixa): ?>
            Com <strong>Todas</strong>, um único arquivo com PM, LP e AB é separado pela coluna de revenda (Cód. unidade venda / UNB) e cada revenda presente é substituída.
        <?php endif; ?>
    </div>
</div>
