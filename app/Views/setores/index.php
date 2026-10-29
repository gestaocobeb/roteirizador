<h1 class="h3 mb-1">Setores e limites</h1>
<p class="text-muted">Limites usados na Prioridade: quantidade máxima de clientes priorizados e de HL por setor, em cada Sala.
    <?php if (!$podeAlterarLimites): ?>
        <br><i class="bi bi-lock"></i> Os limites são definidos pelo Administrador. Um setor novo recebe os limites da Sala.
    <?php endif; ?>
</p>

<?php if ($podeAlterarLimites): ?>
<div class="card mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-3"></i> Salas por revenda</div>
    <div class="card-body">
        <div class="mb-3">
            <?php foreach ($mesasCadastradas as $m): ?>
                <span class="badge text-bg-light border me-1 mb-1">Sala <?= e((string) $m['codigo']) ?> <span class="badge badge-revenda"><?= e($m['revenda']) ?></span></span>
            <?php endforeach; ?>
        </div>
        <form method="post" action="<?= e(base_url('setores/mesas')) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-6 col-md-2">
                <label for="novaMesaCodigo" class="form-label small mb-1">Nova Sala</label>
                <input type="number" class="form-control form-control-sm" id="novaMesaCodigo" name="codigo" min="1" required>
            </div>
            <div class="col-6 col-md-2">
                <label for="novaMesaRevenda" class="form-label small mb-1">Revenda</label>
                <select class="form-select form-select-sm" id="novaMesaRevenda" name="revenda" required>
                    <?php foreach (\App\Helpers\Revenda::siglas() as $sigla): ?>
                        <option value="<?= e($sigla) ?>" <?= $sigla === $revendaAtual ? 'selected' : '' ?>><?= e($sigla) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Cadastrar Sala</button>
            </div>
            <div class="col-12 form-text mt-1">
                Uma Sala pode atender mais de uma revenda: cadastre a mesma Sala em cada revenda (ex.: Sala 23 na LP e na AB).
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($podeAlterarLimites && $mesasLimites): ?>
<div class="card mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-sliders"></i> Limites por Sala</div>
    <div class="card-body">
        <p class="small text-muted mb-3">Aplica o mesmo limite a todos os setores da Sala. Para um setor específico, altere na tabela abaixo.</p>
        <div class="row g-3">
            <?php foreach ($mesasLimites as $mesa => $limites): ?>
                <div class="col-md-6 col-xl-4">
                    <form method="post" action="<?= e(base_url('setores/mesa/' . (int) $mesa . '/limites')) ?>" class="border rounded p-2 h-100"
                          data-confirmar="<?= e('Aplicar estes limites aos ' . $limites['setores'] . ' setor(es) da Sala ' . $mesa . '?') ?>">
                        <?= csrf_field() ?>
                        <div class="fw-semibold mb-2">Sala <?= e((string) $mesa) ?>
                            <?php foreach ($limites['revendas'] as $sigla): ?><span class="badge badge-revenda me-1"><?= e($sigla) ?></span><?php endforeach; ?>
                            <span class="badge text-bg-light"><?= e((string) $limites['setores']) ?> setor(es)</span></div>
                        <div class="row g-2 align-items-end">
                            <div class="col-5">
                                <label class="form-label small mb-1" for="mesaClientes<?= (int) $mesa ?>">Clientes</label>
                                <input type="number" class="form-control form-control-sm" id="mesaClientes<?= (int) $mesa ?>" name="limite_clientes" min="0" max="255" value="<?= e((string) $limites['limite_clientes']) ?>" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label small mb-1" for="mesaHl<?= (int) $mesa ?>">HL</label>
                                <input type="number" class="form-control form-control-sm" id="mesaHl<?= (int) $mesa ?>" name="limite_hl" min="0" step="0.01" value="<?= e((string) $limites['limite_hl']) ?>" required>
                            </div>
                            <div class="col-3">
                                <button type="submit" class="btn btn-sm btn-primary w-100">Aplicar</button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="post" action="<?= e(base_url('setores')) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-6 col-md-2">
                <label for="novoCodigo" class="form-label small mb-1">Setor</label>
                <input type="number" class="form-control form-control-sm" id="novoCodigo" name="codigo" min="1" required>
            </div>
            <div class="col-6 col-md-2">
                <label for="novaMesa" class="form-label small mb-1">Sala</label>
                <select class="form-select form-select-sm" id="novaMesa" name="mesa" required>
                    <?php foreach ($mesasCadastradas as $m): ?>
                        <option value="<?= e($m['codigo'] . '|' . $m['revenda']) ?>"><?= e($m['codigo'] . ' (' . $m['revenda'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($podeAlterarLimites): ?>
                <div class="col-6 col-md-2">
                    <label for="novoLimiteClientes" class="form-label small mb-1">Limite de clientes</label>
                    <input type="number" class="form-control form-control-sm" id="novoLimiteClientes" name="limite_clientes" min="0" max="255" value="5" required>
                </div>
                <div class="col-6 col-md-2">
                    <label for="novoLimiteHl" class="form-label small mb-1">Limite de HL</label>
                    <input type="number" class="form-control form-control-sm" id="novoLimiteHl" name="limite_hl" min="0" step="0.01" value="15" required>
                </div>
            <?php endif; ?>
            <input type="hidden" name="ativo" value="1">
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Adicionar setor</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Revenda</th><th>Setor</th><th>Sala</th><th>Limite de clientes</th><th>Limite de HL</th><th>Ativo</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($setores as $s): ?>
                    <?php $formId = 'setor' . (int) $s['id']; ?>
                    <tr class="<?= (int) $s['ativo'] === 1 ? '' : 'text-muted' ?>">
                        <td><span class="badge badge-revenda"><?= e((string) ($s['revenda'] ?? '?')) ?></span></td>
                        <td class="fw-semibold"><?= e((string) $s['codigo']) ?></td>
                        <td>
                            <select form="<?= e($formId) ?>" name="mesa" class="form-select form-select-sm" style="max-width: 8rem" required aria-label="Sala">
                                <?php foreach ($mesasCadastradas as $m): ?>
                                    <option value="<?= e($m['codigo'] . '|' . $m['revenda']) ?>" <?= (int) $m['codigo'] === (int) $s['mesa'] && $m['revenda'] === $s['revenda'] ? 'selected' : '' ?>><?= e($m['codigo'] . ' (' . $m['revenda'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <?php if ($podeAlterarLimites): ?>
                            <td><input form="<?= e($formId) ?>" type="number" name="limite_clientes" min="0" max="255" class="form-control form-control-sm" style="max-width: 6rem" value="<?= e((string) $s['limite_clientes']) ?>" required></td>
                            <td><input form="<?= e($formId) ?>" type="number" name="limite_hl" min="0" step="0.01" class="form-control form-control-sm" style="max-width: 7rem" value="<?= e((string) $s['limite_hl']) ?>" required></td>
                        <?php else: ?>
                            <td><?= e((string) $s['limite_clientes']) ?></td>
                            <td><?= e(fmt_hl((float) $s['limite_hl'])) ?></td>
                        <?php endif; ?>
                        <td>
                            <div class="form-check form-switch">
                                <input form="<?= e($formId) ?>" class="form-check-input" type="checkbox" name="ativo" value="1" aria-label="Ativo" <?= (int) $s['ativo'] === 1 ? 'checked' : '' ?>>
                            </div>
                        </td>
                        <td class="text-end">
                            <form method="post" action="<?= e(base_url('setores/' . (int) $s['id'])) ?>" id="<?= e($formId) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Salvar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
