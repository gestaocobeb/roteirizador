<?php $acao = 'critica'; $permiteTodas = true; $filtroRevenda = true; ?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">Crítica GOD</h1>
        <p class="text-muted mb-0">
            Pedidos aguardando roteirização para <strong><?= e(fmt_data($dataRef)) ?></strong>
            &middot; <?= $revenda !== null ? 'Revenda ' . e($revenda) : 'todas as revendas' ?>
            <?= $mesa !== null ? '&middot; Sala ' . e((string) $mesa) : '&middot; todas as Salas' ?>
        </p>
    </div>
    <?php require dirname(__DIR__) . '/partials/filtro-data.php'; ?>
</div>

<?php if (!$linhas): ?>
    <div class="alert alert-info">Nenhum pedido do Cora importado para esta data<?= $mesa !== null ? ' nesta Sala' : '' ?>.</div>
    <?php return; ?>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-muted">Clientes</div><div class="fs-4 fw-semibold"><?= e((string) $totais['clientes']) ?></div></div></div>
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-muted">Valor dos pedidos</div><div class="fs-4 fw-semibold"><?= e(fmt_moeda($totais['valor'])) ?></div></div></div>
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-muted">Volume</div><div class="fs-4 fw-semibold"><?= e(fmt_hl($totais['hl'])) ?> HL</div></div></div>
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-muted">Decididos</div><div class="fs-4 fw-semibold"><span id="contadorDecididos"><?= e((string) $totais['decididos']) ?></span> / <?= e((string) $totais['clientes']) ?></div></div></div>
    <div class="col-6 col-lg"><div class="card card-body py-2"><div class="small text-muted">Com alerta</div><div class="fs-4 fw-semibold"><?= e((string) $totais['com_alerta']) ?></div></div></div>
</div>

<div class="card mb-3 d-print-none" id="filtrosCritica">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-4 col-xl-3">
                <label for="filtroTexto" class="form-label small mb-1">Busca</label>
                <input type="search" class="form-control form-control-sm" id="filtroTexto" placeholder="Código, cliente ou cidade">
            </div>
            <div class="col-6 col-md-auto">
                <label for="filtroSetor" class="form-label small mb-1">Setor</label>
                <select class="form-select form-select-sm" id="filtroSetor">
                    <option value="">Todos</option>
                    <?php foreach ($setoresNaTela as $s): ?>
                        <option value="<?= e((string) $s) ?>">Setor <?= e((string) $s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label for="filtroDecisao" class="form-label small mb-1">Decisão</label>
                <select class="form-select form-select-sm" id="filtroDecisao">
                    <option value="">Todas</option>
                    <option value="pendente">Pendentes</option>
                    <?php foreach ($decisoes as $valor => $rotulo): ?>
                        <option value="<?= e($valor) ?>"><?= e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label for="filtroValorMin" class="form-label small mb-1">Valor (R$)</label>
                <div class="input-group input-group-sm faixa-filtro">
                    <input type="number" class="form-control" id="filtroValorMin" min="0" step="0.01" placeholder="mín." aria-label="Valor mínimo">
                    <input type="number" class="form-control" id="filtroValorMax" min="0" step="0.01" placeholder="máx." aria-label="Valor máximo">
                </div>
            </div>
            <div class="col-auto">
                <label for="filtroHlMin" class="form-label small mb-1">HL</label>
                <div class="input-group input-group-sm faixa-filtro">
                    <input type="number" class="form-control" id="filtroHlMin" min="0" step="0.01" placeholder="mín." aria-label="HL mínimo">
                    <input type="number" class="form-control" id="filtroHlMax" min="0" step="0.01" placeholder="máx." aria-label="HL máximo">
                </div>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="limparFiltros"><i class="bi bi-x-circle"></i> Limpar</button>
            </div>
            <div class="col-auto ms-auto small text-muted pb-1"><span id="contadorVisiveis"><?= e((string) $totais['clientes']) ?></span> exibidos</div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mt-2 pt-2 border-top">
            <span class="small text-muted me-1">Indicadores:</span>
            <?php foreach ($indicadores as $codigo => [$rotulo, $cor]): ?>
                <input type="checkbox" class="btn-check filtro-indicador" id="ind_<?= e($codigo) ?>" value="<?= e($codigo) ?>" autocomplete="off">
                <label class="btn btn-sm btn-outline-<?= e($cor) ?>" for="ind_<?= e($codigo) ?>">
                    <?= e($rotulo) ?> <span class="badge text-bg-light ms-1"><?= e((string) ($contagemIndicadores[$codigo] ?? 0)) ?></span>
                </label>
            <?php endforeach; ?>
            <div class="vr mx-1"></div>
            <input type="checkbox" class="btn-check" id="filtroAlertas" autocomplete="off">
            <label class="btn btn-sm btn-outline-dark" for="filtroAlertas">Qualquer alerta</label>
            <input type="checkbox" class="btn-check" id="filtroSemIndicador" autocomplete="off">
            <label class="btn btn-sm btn-outline-dark" for="filtroSemIndicador">Sem alerta</label>
            <div class="form-check form-check-inline small ms-2 mb-0">
                <input class="form-check-input" type="radio" name="modoIndicador" id="modoQualquer" value="qualquer" checked>
                <label class="form-check-label" for="modoQualquer">tem algum dos marcados</label>
            </div>
            <div class="form-check form-check-inline small mb-0">
                <input class="form-check-input" type="radio" name="modoIndicador" id="modoTodos" value="todos">
                <label class="form-check-label" for="modoTodos">tem todos</label>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="table-responsive tabela-critica">
        <table class="table table-sm table-hover mb-0 align-middle" data-filtro-tabela>
            <thead class="table-light sticky-top">
                <tr>
                    <th>Cód.</th>
                    <th>Cliente</th>
                    <th>Cidade</th>
                    <th>Setor</th>
                    <th>Visita</th>
                    <th>Indicadores</th>
                    <th class="text-end">Pedidos</th>
                    <th class="text-end">Valor</th>
                    <th class="text-end">HL</th>
                    <th class="text-end" title="Linhas de nota no relatório 03.02.37">Já comprou?</th>
                    <th style="min-width: 22rem">Decisão</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($linhas as $l): ?>
                    <tr data-busca="<?= e(mb_strtolower($l['revenda'] . ' ' . $l['cliente_codigo'] . ' ' . $l['nome'] . ' ' . $l['razao_social'] . ' ' . $l['cidade'])) ?>"
                        data-setor="<?= e((string) $l['setor_codigo']) ?>"
                        data-pendente="<?= $l['decisao'] === null ? '1' : '0' ?>"
                        data-decisao="<?= e($l['decisao'] ?? 'pendente') ?>"
                        data-alerta="<?= $l['alertas'] ? '1' : '0' ?>"
                        data-indicadores="<?= e(implode(' ', $l['indicadores'])) ?>"
                        data-valor="<?= e(number_format((float) $l['valor_pedido'], 2, '.', '')) ?>"
                        data-hl="<?= e(number_format((float) $l['volume_hl'], 4, '.', '')) ?>"
                        class="<?= $l['decisao'] !== null ? 'linha-decidida' : '' ?>">
                        <td class="fw-semibold text-nowrap">
                            <?php if ($revenda === null): ?><span class="badge badge-revenda"><?= e($l['revenda']) ?></span><?php endif; ?>
                            <?= e((string) $l['cliente_codigo']) ?>
                        </td>
                        <td>
                            <?= e($l['nome']) ?>
                            <?php if ($l['razao_social'] && $l['razao_social'] !== $l['nome']): ?>
                                <div class="small text-muted text-truncate" style="max-width: 16rem"><?= e($l['razao_social']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($l['cidade']) ?></td>
                        <td><?= e((string) $l['setor_codigo']) ?></td>
                        <td class="small"><?= e(rtrim((string) $l['dia_visita'], '/ ')) ?></td>
                        <td>
                            <?php foreach ($l['indicadores'] as $codigo): ?>
                                <?php
                                [$rotulo, $cor] = $indicadores[$codigo];
                                $detalhe = match ($codigo) {
                                    'trava' => trim($l['trava_condicao'] . ' ' . $l['trava_motivo']),
                                    'fora_rota' => (string) $l['fora_rota'],
                                    'black_list' => $l['black_list'] . ' ocorrência(s)',
                                    default => '',
                                };
                                ?>
                                <span class="badge text-bg-<?= e($cor) ?>" <?= $detalhe !== '' ? 'title="' . e($detalhe) . '" data-bs-toggle="tooltip"' : '' ?>><?= e($rotulo) ?></span>
                            <?php endforeach; ?>
                            <?php if ($l['criticas']): ?>
                                <i class="bi bi-info-circle text-muted" title="<?= e($l['criticas']) ?>" data-bs-toggle="tooltip"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= e((string) $l['qtd_pedidos']) ?></td>
                        <td class="text-end text-nowrap"><?= e(fmt_moeda($l['valor_pedido'])) ?></td>
                        <td class="text-end"><?= e(fmt_hl($l['volume_hl'])) ?></td>
                        <td class="text-end"><?= e((string) $l['compras']) ?></td>
                        <td>
                            <?php if ($podeDecidir): ?>
                                <form method="post" action="<?= e(base_url('critica/decisao')) ?>" class="d-flex gap-1" data-ajax>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="data" value="<?= e($dataRef) ?>">
                                    <input type="hidden" name="revenda" value="<?= e($l['revenda']) ?>">
                                    <input type="hidden" name="cliente" value="<?= e((string) $l['cliente_codigo']) ?>">
                                    <select name="decisao" class="form-select form-select-sm" aria-label="Decisão">
                                        <option value="">Pendente</option>
                                        <?php foreach ($decisoes as $valor => $rotulo): ?>
                                            <option value="<?= e($valor) ?>" <?= $l['decisao'] === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="observacao" class="form-control form-control-sm" maxlength="255" placeholder="Observação" value="<?= e($l['decisao_observacao']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Salvar"><i class="bi bi-check-lg"></i></button>
                                </form>
                            <?php elseif ($l['decisao'] !== null): ?>
                                <strong><?= e($decisoes[$l['decisao']] ?? $l['decisao']) ?></strong>
                                <?php if ($l['decisao_observacao']): ?><span class="small text-muted">&middot; <?= e($l['decisao_observacao']) ?></span><?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small">Pendente</span>
                            <?php endif; ?>
                            <?php if ($l['decidido_por']): ?>
                                <div class="small text-muted info-decisao"><?= e($l['decidido_por']) ?> &middot; <?= e(fmt_data_hora($l['decidido_em'])) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-geo-alt"></i> Mapa Sinergia &mdash; PDVs por cidade
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Cidade</th><th class="text-end">PDVs</th><th class="text-end">HL</th><th class="text-end">Valor</th></tr>
            </thead>
            <tbody>
                <?php foreach ($sinergia as $posicao => $cidade): ?>
                    <tr>
                        <td><?= e((string) ($posicao + 1)) ?></td>
                        <td><?= e($cidade['cidade']) ?></td>
                        <td class="text-end"><?= e((string) $cidade['pdvs']) ?></td>
                        <td class="text-end"><?= e(fmt_hl($cidade['hl'])) ?></td>
                        <td class="text-end"><?= e(fmt_moeda($cidade['valor'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
