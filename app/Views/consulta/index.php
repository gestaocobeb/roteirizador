<?php $acao = 'consulta'; $permiteTodas = true; ?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">Consulta do dia</h1>
        <p class="text-muted mb-0">
            Consolidado para roteirização da entrega de <strong><?= e(fmt_data($dataRef)) ?></strong>
            &middot; <?= $revenda !== null ? 'Revenda ' . e($revenda) : 'todas as revendas' ?>
            <?= $mesa !== null ? '&middot; Sala ' . e((string) $mesa) : '&middot; todas as Salas' ?>
        </p>
    </div>
    <div class="d-flex gap-2 align-items-end">
        <?php require dirname(__DIR__) . '/partials/filtro-data.php'; ?>
        <button type="button" class="btn btn-sm btn-outline-secondary d-print-none" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card card-body py-2"><div class="small text-muted">Clientes com pedido</div><div class="fs-4 fw-semibold"><?= e((string) $totalClientes) ?></div></div></div>
    <div class="col-md-3"><div class="card card-body py-2"><div class="small text-muted">Priorizados</div><div class="fs-4 fw-semibold"><?= e((string) count($prioridades)) ?></div></div></div>
    <div class="col-md-3"><div class="card card-body py-2"><div class="small text-muted">Decisões da Crítica</div><div class="fs-4 fw-semibold"><?= e((string) count($decididos)) ?></div></div></div>
    <div class="col-md-3">
        <div class="card card-body py-2">
            <div class="small text-muted">Pendentes por Sala</div>
            <div class="small">
                <?php if (!$pendentesPorMesa): ?>&mdash;<?php endif; ?>
                <?php foreach ($pendentesPorMesa as $m => $c): ?>
                    <span class="me-2 <?= $c['pendentes'] > 0 ? 'text-danger' : 'text-success' ?>">Sala <?= e((string) $m) ?>: <?= e((string) $c['pendentes']) ?>/<?= e((string) $c['total']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-flag"></i> Clientes priorizados</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Sala</th><th>Setor</th><th>Cód.</th><th>Cliente</th><th>Cidade</th><th class="text-end">HL</th><th>Observação</th><th>Trava</th></tr>
            </thead>
            <tbody>
                <?php if (!$prioridades): ?>
                    <tr><td colspan="8" class="text-muted text-center py-3">Nenhuma prioridade registrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($prioridades as $p): ?>
                    <tr>
                        <td><?= e((string) $p['mesa']) ?></td>
                        <td><?= e((string) $p['setor_codigo']) ?></td>
                        <td class="fw-semibold text-nowrap"><?php if ($revenda === null): ?><span class="badge badge-revenda"><?= e($p['revenda']) ?></span> <?php endif; ?><?= e((string) $p['cliente_codigo']) ?></td>
                        <td><?= e($p['nome']) ?></td>
                        <td><?= e($p['cidade']) ?></td>
                        <td class="text-end"><?= e(fmt_hl($p['volume_hl'])) ?></td>
                        <td><?= e($p['observacao']) ?></td>
                        <td class="small"><?= e($p['trava_condicao']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard-check"></i> Decisões da Crítica GOD</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Sala</th><th>Cód.</th><th>Cliente</th><th>Cidade</th><th class="text-end">Valor</th><th class="text-end">HL</th><th>Decisão</th><th>Observação</th><th>Por</th></tr>
            </thead>
            <tbody>
                <?php if (!$decididos): ?>
                    <tr><td colspan="9" class="text-muted text-center py-3">Nenhuma decisão registrada.</td></tr>
                <?php endif; ?>
                <?php foreach ($decididos as $d): ?>
                    <tr class="<?= in_array($d['decisao'], ['corta', 'abaixo_100_corta'], true) ? 'table-danger' : '' ?>">
                        <td><?= e((string) $d['gerente_codigo']) ?></td>
                        <td class="fw-semibold text-nowrap"><?php if ($revenda === null): ?><span class="badge badge-revenda"><?= e($d['revenda']) ?></span> <?php endif; ?><?= e((string) $d['cliente_codigo']) ?></td>
                        <td><?= e($d['nome']) ?></td>
                        <td><?= e($d['cidade']) ?></td>
                        <td class="text-end text-nowrap"><?= e(fmt_moeda($d['valor_pedido'])) ?></td>
                        <td class="text-end"><?= e(fmt_hl($d['volume_hl'])) ?></td>
                        <td class="fw-semibold"><?= e($decisoes[$d['decisao']] ?? $d['decisao']) ?></td>
                        <td><?= e($d['decisao_observacao']) ?></td>
                        <td class="small text-muted"><?= e($d['decidido_por']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-lock"></i> Clientes com trava nos pedidos do dia</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Cód.</th><th>Cliente</th><th>Setor</th><th>Condição de entrega</th><th>Motivo</th></tr>
            </thead>
            <tbody>
                <?php if (!$comTrava): ?>
                    <tr><td colspan="5" class="text-muted text-center py-3">Nenhum cliente com trava entre os pedidos.</td></tr>
                <?php endif; ?>
                <?php foreach ($comTrava as $t): ?>
                    <tr>
                        <td class="fw-semibold text-nowrap"><?php if ($revenda === null): ?><span class="badge badge-revenda"><?= e($t['revenda']) ?></span> <?php endif; ?><?= e((string) $t['cliente_codigo']) ?></td>
                        <td><?= e($t['nome']) ?></td>
                        <td><?= e((string) $t['setor_codigo']) ?></td>
                        <td><?= e($t['trava_condicao']) ?></td>
                        <td><?= e($t['trava_motivo']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
