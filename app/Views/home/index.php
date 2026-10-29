<?php $acao = ''; ?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Painel</h1>
        <p class="text-muted mb-0">Situação dos dados para a entrega de <strong><?= e(fmt_data($dataRef)) ?></strong></p>
    </div>
    <?php require dirname(__DIR__) . '/partials/filtro-data.php'; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100 <?= $corasDaData ? 'border-success' : 'border-warning' ?>">
            <div class="card-body">
                <div class="text-muted small">Pedidos Cora da data</div>
                <?php if ($corasDaData): ?>
                    <div class="fs-3 fw-semibold"><?= e((string) $totalClientes) ?> clientes<?= $mesa !== null ? ' na Sala ' . e((string) $mesa) : '' ?></div>
                    <?php foreach ($corasDaData as $siglaRevenda => $cora): ?>
                        <div class="small text-muted"><span class="badge badge-revenda"><?= e($siglaRevenda) ?></span> importado em <?= e(fmt_data_hora($cora['criado_em'])) ?> por <?= e($cora['usuario_nome']) ?></div>
                    <?php endforeach; ?>
                    <?php foreach (array_diff($revendasNaTela, array_keys($corasDaData)) as $siglaRevenda): ?>
                        <div class="small text-warning-emphasis"><span class="badge badge-revenda"><?= e($siglaRevenda) ?></span> ainda não importado</div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="fs-5 fw-semibold text-warning-emphasis">Ainda não importado</div>
                    <div class="small text-muted">A Logística precisa importar o export do Cora para esta data.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (isset($abasMenu['critica'])): ?>
    <div class="col-md-4">
        <a href="<?= e(base_url('critica?data=' . urlencode($dataRef))) ?>" class="card h-100 text-decoration-none">
            <div class="card-body">
                <div class="text-muted small">Crítica GOD</div>
                <div class="fs-5 fw-semibold text-body"><i class="bi bi-clipboard-check"></i> Analisar pedidos</div>
                <div class="small text-muted">Indicadores por cliente e decisão da Logística</div>
            </div>
        </a>
    </div>
    <?php endif; ?>
    <?php if (isset($abasMenu['prioridade'])): ?>
    <div class="col-md-4">
        <a href="<?= e(base_url('prioridade?data=' . urlencode($dataRef))) ?>" class="card h-100 text-decoration-none">
            <div class="card-body">
                <div class="text-muted small">Prioridade</div>
                <div class="fs-5 fw-semibold text-body"><i class="bi bi-flag"></i> <?= e((string) $totalPrioridades) ?> clientes priorizados</div>
                <div class="small text-muted">Limites de clientes e HL por setor</div>
            </div>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($mesa === null): ?>
<div class="card">
    <div class="card-header bg-white fw-semibold">Última atualização de cada base</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Revenda</th><th>Base</th><th>Última importação</th><th>Arquivo</th><th class="text-end">Registros</th><th>Responsável</th></tr>
            </thead>
            <tbody>
                <?php foreach ($revendasNaTela as $siglaRevenda): ?>
                <?php foreach ($tipos as $tipo => $descricao): ?>
                    <?php $ultima = $ultimasPorTipo[$siglaRevenda][$tipo] ?? null; ?>
                    <tr>
                        <td><span class="badge badge-revenda"><?= e($siglaRevenda) ?></span></td>
                        <td><?= e($descricao) ?></td>
                        <?php if ($ultima): ?>
                            <td>
                                <?= e(fmt_data_hora($ultima['criado_em'])) ?>
                                <?php if ($ultima['data_referencia']): ?>
                                    <span class="badge text-bg-light">entrega <?= e(fmt_data($ultima['data_referencia'])) ?></span>
                                <?php endif; ?>
                                <?php if ($ultima['situacao'] === 'excluida'): ?>
                                    <span class="badge text-bg-danger">Excluída em <?= e(fmt_data_hora($ultima['excluida_em'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= e($ultima['arquivo_nome']) ?></td>
                            <td class="text-end"><?= e(number_format((int) $ultima['total_linhas'], 0, ',', '.')) ?></td>
                            <td><?= e($ultima['usuario_nome']) ?></td>
                        <?php else: ?>
                            <td colspan="4" class="text-muted">Nunca importado</td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
