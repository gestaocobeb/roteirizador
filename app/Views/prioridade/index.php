<?php $acao = 'prioridade'; $permiteTodas = false; ?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">Prioridade &mdash; Sala <?= e((string) $mesa) ?>
            <?php foreach ($revendasMesa as $sigla): ?><span class="badge badge-revenda align-middle fs-6 ms-1"><?= e($sigla) ?></span><?php endforeach; ?>
        </h1>
        <p class="text-muted mb-0">Entrega de <strong><?= e(fmt_data($dataRef)) ?></strong>. O HL vem dos pedidos do Cora importados para a data.</p>
    </div>
    <?php require dirname(__DIR__) . '/partials/filtro-data.php'; ?>
</div>

<?php if (!$setores): ?>
    <div class="alert alert-warning">Nenhum setor ativo cadastrado para esta Sala. A Logística pode cadastrá-los em Setores.</div>
    <?php return; ?>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ($setores as $s): ?>
        <?php
        // Limites do dia = limite do setor ajustado pelas transferências da data.
        $hlDisponivel = (float) $s['limite_hl_dia'] - (float) $s['hl_utilizado'];
        $clientesDisponiveis = (int) $s['limite_clientes_dia'] - (int) $s['clientes'];
        $pctHl = percentual($s['hl_utilizado'], $s['limite_hl_dia']);
        $pctClientes = percentual($s['clientes'], $s['limite_clientes_dia']);
        $sinal = fn ($valor) => $valor > 0 ? '+' : '−';
        ?>
        <div class="col-6 col-md-4 col-xl-3">
            <div class="card h-100">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-baseline">
                        <span class="fw-semibold">Setor <?= e((string) $s['codigo']) ?><?php if (count($revendasMesa) > 1): ?> <span class="badge badge-revenda"><?= e($s['revenda']) ?></span><?php endif; ?></span>
                        <span class="small <?= $hlDisponivel <= 0 || $clientesDisponiveis <= 0 ? 'text-danger' : 'text-success' ?>">
                            <?= e(fmt_hl($hlDisponivel)) ?> HL livres
                        </span>
                    </div>

                    <div class="small text-muted mt-2">
                        Clientes: <?= e((string) $s['clientes']) ?> / <?= e((string) $s['limite_clientes_dia']) ?>
                        <?php if ((int) $s['ajuste_clientes'] !== 0): ?>
                            <span class="badge <?= $s['ajuste_clientes'] > 0 ? 'text-bg-success' : 'text-bg-secondary' ?>" title="Transferência do dia (limite do setor: <?= e((string) $s['limite_clientes']) ?>)" data-bs-toggle="tooltip"><?= e($sinal($s['ajuste_clientes']) . abs((int) $s['ajuste_clientes'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="progress" style="height: 6px" role="progressbar" aria-valuenow="<?= e((string) $pctClientes) ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar <?= $pctClientes >= 100 ? 'bg-danger' : '' ?>" style="width: <?= e((string) $pctClientes) ?>%"></div>
                    </div>

                    <div class="small text-muted mt-2">
                        HL: <?= e(fmt_hl($s['hl_utilizado'])) ?> / <?= e(fmt_hl($s['limite_hl_dia'])) ?>
                        <?php if ((float) $s['ajuste_hl'] !== 0.0): ?>
                            <span class="badge <?= $s['ajuste_hl'] > 0 ? 'text-bg-success' : 'text-bg-secondary' ?>" title="Transferência do dia (limite do setor: <?= e(fmt_hl($s['limite_hl'])) ?>)" data-bs-toggle="tooltip"><?= e($sinal($s['ajuste_hl']) . fmt_hl(abs((float) $s['ajuste_hl']))) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="progress" style="height: 6px" role="progressbar" aria-valuenow="<?= e((string) $pctHl) ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar <?= $pctHl >= 100 ? 'bg-danger' : ($pctHl >= 80 ? 'bg-warning' : '') ?>" style="width: <?= e((string) $pctHl) ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($podeAlterar): ?>
    <div class="card mb-4 d-print-none">
        <div class="card-body">
            <form method="post" action="<?= e(base_url('prioridade')) ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="data" value="<?= e($dataRef) ?>">
                <input type="hidden" name="mesa" value="<?= e((string) $mesa) ?>">
                <div class="col-sm-3 col-lg-2">
                    <label for="cliente" class="form-label small mb-1">Código do cliente</label>
                    <input type="number" class="form-control" id="cliente" name="cliente" min="1" required autofocus>
                </div>
                <div class="col-sm">
                    <label for="observacao" class="form-label small mb-1">Observação para a entrega (opcional)</label>
                    <input type="text" class="form-control" id="observacao" name="observacao" maxlength="255" placeholder="Ex.: Entregar na parte da manhã, até as 10:30h">
                </div>
                <div class="col-sm-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-flag"></i> Priorizar</button>
                </div>
            </form>
            <div class="form-text">O setor é identificado automaticamente pelo pedido do Cora. O sistema bloqueia se o setor estourar o limite de clientes ou de HL.</div>
        </div>
    </div>
<?php elseif ($perfil === 'logistica'): ?>
    <div class="alert alert-light border small">Consulta: o registro de prioridades é feito pelo Comercial de cada Sala.</div>
<?php endif; ?>

<?php if (($podeAlterar && count($setores) > 1) || $transferencias): ?>
    <div class="card mb-4 d-print-none">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-arrow-left-right"></i> Transferir saldo entre setores da Sala <?= e((string) $mesa) ?></div>
        <div class="card-body">
            <?php if ($podeAlterar && count($setores) > 1): ?>
                <form method="post" action="<?= e(base_url('prioridade/transferir')) ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="data" value="<?= e($dataRef) ?>">
                    <input type="hidden" name="mesa" value="<?= e((string) $mesa) ?>">
                    <div class="col-6 col-md-3 col-xl-2">
                        <label for="transfOrigem" class="form-label small mb-1">Do setor</label>
                        <select class="form-select form-select-sm" id="transfOrigem" name="origem" required>
                            <?php foreach ($setores as $s): ?>
                                <option value="<?= e((string) $s['codigo']) ?>"><?= e($s['codigo'] . ' (livre: ' . $s['clientes_livres'] . ' cli. / ' . fmt_hl($s['hl_livre']) . ' HL)') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 col-xl-2">
                        <label for="transfDestino" class="form-label small mb-1">Para o setor</label>
                        <select class="form-select form-select-sm" id="transfDestino" name="destino" required>
                            <?php foreach ($setores as $i => $s): ?>
                                <option value="<?= e((string) $s['codigo']) ?>" <?= $i === 1 ? 'selected' : '' ?>><?= e((string) $s['codigo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 col-xl-1">
                        <label for="transfClientes" class="form-label small mb-1">Clientes</label>
                        <input type="number" class="form-control form-control-sm" id="transfClientes" name="clientes" min="0" max="255" value="0">
                    </div>
                    <div class="col-6 col-md-2 col-xl-1">
                        <label for="transfHl" class="form-label small mb-1">HL</label>
                        <input type="number" class="form-control form-control-sm" id="transfHl" name="hl" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-arrow-left-right"></i> Transferir</button>
                    </div>
                </form>
                <div class="form-text">Vale somente para a entrega de <?= e(fmt_data($dataRef)) ?>; o limite cadastrado do setor não muda. Só é possível transferir o saldo ainda livre do setor de origem.</div>
            <?php endif; ?>

            <?php if ($transferencias): ?>
                <table class="table table-sm mb-0 mt-3 align-middle">
                    <thead class="table-light">
                        <tr><th>De</th><th>Para</th><th class="text-end">Clientes</th><th class="text-end">HL</th><th>Registrado por</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transferencias as $t): ?>
                            <tr>
                                <td>Setor <?= e((string) $t['setor_origem_codigo']) ?></td>
                                <td>Setor <?= e((string) $t['setor_destino_codigo']) ?></td>
                                <td class="text-end"><?= e((string) $t['clientes']) ?></td>
                                <td class="text-end"><?= e(fmt_hl($t['hl'])) ?></td>
                                <td class="small text-muted"><?= e($t['usuario_nome']) ?> &middot; <?= e(fmt_data_hora($t['criado_em'])) ?></td>
                                <td class="text-end">
                                    <?php if ($podeAlterar): ?>
                                        <form method="post" action="<?= e(base_url('prioridade/transferencias/' . (int) $t['id'] . '/remover')) ?>" data-confirmar="Desfazer a transferência do setor <?= e((string) $t['setor_origem_codigo']) ?> para o <?= e((string) $t['setor_destino_codigo']) ?>?">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Desfazer"><i class="bi bi-arrow-counterclockwise"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-white fw-semibold">Clientes priorizados (<?= e((string) count($prioridades)) ?>)</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Cód.</th><th>Cliente</th><th>Cidade</th><th>Setor</th><th class="text-end">HL</th><th>Observação</th><th>Registrado por</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (!$prioridades): ?>
                    <tr><td colspan="8" class="text-muted text-center py-3">Nenhum cliente priorizado nesta Sala para a data.</td></tr>
                <?php endif; ?>
                <?php foreach ($prioridades as $p): ?>
                    <tr>
                        <td class="fw-semibold"><?= e((string) $p['cliente_codigo']) ?></td>
                        <td>
                            <?= e($p['nome']) ?>
                            <?php if ($p['trava_condicao']): ?>
                                <span class="badge text-bg-warning" title="<?= e($p['trava_condicao']) ?>" data-bs-toggle="tooltip">Trava</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($p['cidade']) ?></td>
                        <td><?= e((string) $p['setor_codigo']) ?></td>
                        <td class="text-end"><?= e(fmt_hl($p['volume_hl'])) ?></td>
                        <td><?= e($p['observacao']) ?></td>
                        <td class="small text-muted"><?= e($p['usuario_nome']) ?> &middot; <?= e(fmt_data_hora($p['criado_em'])) ?></td>
                        <td class="text-end">
                            <?php if ($podeAlterar): ?>
                                <form method="post" action="<?= e(base_url('prioridade/' . (int) $p['id'] . '/remover')) ?>" data-confirmar="Remover a prioridade do cliente <?= e((string) $p['cliente_codigo']) ?>?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remover"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
