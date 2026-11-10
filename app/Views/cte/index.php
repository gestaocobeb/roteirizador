<?php

use App\Helpers\Cte;
use App\Helpers\Revenda;

$fmtInt = fn ($v) => number_format((float) $v, 0, ',', '.');
$totalValor = array_sum(array_map(fn ($l) => (float) $l['valor'], $linhas));
$semValor = count(array_filter($linhas, fn ($l) => $l['valor'] === null));
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">CTE</h1>
        <p class="text-muted mb-0">Planilha do CT-e por revenda: mapa, placa, frete, valor, entregas, peso, cidade e motorista</p>
    </div>
    <?php if (count($revendas) > 1): ?>
        <ul class="nav nav-pills">
            <?php foreach ($revendas as $sigla): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $sigla === $revenda ? 'active' : '' ?>" href="<?= e(base_url('cte?revenda=' . $sigla)) ?>"><?= e($sigla) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><strong>Atualizar <?= e($revenda) ?> - <?= e(Revenda::nome($revenda)) ?></strong></div>
            <div class="card-body">
                <form method="post" action="<?= e(base_url('cte/importar')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="revenda" value="<?= e($revenda) ?>">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="mapas" class="form-label">03.11.49.02 (arquivo)</label>
                            <input type="file" class="form-control" id="mapas" name="mapas" accept=".xlsx,.xlsm,.csv,.inf,.txt">
                            <div class="form-text">Opcional se já enviado na Tirinha: o 03.11.49.02 da Tirinha também atualiza o CTE.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="valores" class="form-label">03.03.12: valor dos mapas</label>
                            <textarea class="form-control font-monospace small" id="valores" name="valores" rows="5"
                                      placeholder="398891  A    994  09/11/2026  09/11/2026  1  59.772,00  056  Liberado"></textarea>
                            <div class="form-text">Copie da tela do 03.03.12 e cole aqui, como na aba "03_03_12" da planilha.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="motoristas" class="form-label">Motoristas: mapa, código e nome</label>
                            <textarea class="form-control font-monospace small" id="motoristas" name="motoristas" rows="5"
                                      placeholder="398891  197  LEANDRO GOMES&#10;398894  195  WELLINGTON MIRANDA"></textarea>
                            <div class="form-text">Uma linha por mapa: o mapa primeiro; depois o código e o nome, em qualquer ordem (pode colar do Excel). Preenche Motorista e Código.</div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-arrow-repeat"></i> Atualizar CTE</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><strong>Como o CTE é montado</strong></div>
            <div class="card-body small">
                <ul class="mb-0">
                    <li>Uma linha por <strong>placa</strong>: o 1º mapa da placa no 03.11.49.02 (recargas não entram), em ordem de mapa.</li>
                    <li><strong>Pedido, Peso e Cidade:</strong> Entregas, Total peso e Cidades do 03.11.49.02.</li>
                    <li><strong>Valor:</strong> do 03.03.12 colado. <strong>Preço frete - cód. cidade:</strong> da tabela de fretes de <?= e($revenda) ?>, pelas cidades do mapa.</li>
                    <li><strong>Motorista e Código:</strong> colados (mapa, código e nome) ou digitados na tabela. <strong>Ct'e:</strong> digitado.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if (!$linhas): ?>
    <div class="alert alert-info">Nenhum mapa de <?= e($revenda) ?> no CTE. Envie o 03.11.49.02 aqui ou gere a Tirinha da revenda.</div>
<?php else: ?>
    <?php if ($semFrete): ?>
        <div class="alert alert-warning py-2 small">
            <?= e((string) count($semFrete)) ?> cidade(s) sem frete na tabela de <?= e($revenda) ?>: <?= e(implode('; ', $semFrete)) ?>.
            <a href="#fretes" class="alert-link">Cadastrar frete</a>
        </div>
    <?php endif; ?>
    <?php if ($semValor): ?>
        <div class="alert alert-warning py-2 small"><?= e((string) $semValor) ?> mapa(s) sem valor: cole o 03.03.12.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('cte/salvar')) ?>" class="card mb-4">
        <?= csrf_field() ?>
        <input type="hidden" name="revenda" value="<?= e($revenda) ?>">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <strong>CT-e <?= e($revenda) ?><?= $dataEntrega ? ' - entrega ' . e(fmt_data($dataEntrega)) : '' ?> <span class="badge text-bg-secondary"><?= e((string) count($linhas)) ?> placa(s)</span></strong>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check2"></i> Salvar</button>
                <button type="submit" name="exportar" value="1" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel"></i> Salvar e exportar Excel</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th style="min-width: 7rem">Ct'e</th>
                        <th>Mapa</th>
                        <th>Placa</th>
                        <th>Preço frete - cód. cidade</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Pedido</th>
                        <th class="text-end">Peso</th>
                        <th>Cidade</th>
                        <th style="min-width: 11rem">Motorista</th>
                        <th style="min-width: 6rem">Código</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($linhas as $l): ?>
                        <?php $id = (int) $l['id']; ?>
                        <tr>
                            <td><input type="text" class="form-control form-control-sm" name="linhas[<?= e((string) $id) ?>][cte_numero]" maxlength="30" value="<?= e((string) $l['cte_numero']) ?>"></td>
                            <td class="fw-bold"><?= e($l['numero_mapa']) ?></td>
                            <td><?= e($l['placa']) ?></td>
                            <td>
                                <?php if ($l['frete']): ?>
                                    <?= e(Cte::freteTexto($l['frete'])) ?>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">sem frete</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= $l['valor'] === null ? '<span class="text-muted">—</span>' : e(number_format((float) $l['valor'], 2, ',', '.')) ?></td>
                            <td class="text-end"><?= e($fmtInt($l['entregas'])) ?></td>
                            <td class="text-end"><?= e($fmtInt($l['peso'])) ?></td>
                            <td class="small"><?= e((string) $l['cidades']) ?></td>
                            <td>
                                <input type="text" class="form-control form-control-sm text-uppercase" name="linhas[<?= e((string) $id) ?>][motorista_nome]" maxlength="100" value="<?= e((string) $l['motorista_nome']) ?>">
                                <?php if ($l['motorista_promax']): ?>
                                    <div class="form-text mt-0">Promax: <?= e((string) $l['motorista_promax']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" name="linhas[<?= e((string) $id) ?>][motorista_codigo]" maxlength="20" value="<?= e((string) $l['motorista_codigo']) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="4">Total</td>
                        <td class="text-end"><?= e(number_format($totalValor, 2, ',', '.')) ?></td>
                        <td class="text-end"><?= e($fmtInt(array_sum(array_column($linhas, 'entregas')))) ?></td>
                        <td class="text-end"><?= e($fmtInt(array_sum(array_column($linhas, 'peso')))) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </form>
<?php endif; ?>

<div class="card" id="fretes">
    <div class="card-header"><strong>Tabela de fretes - <?= e($revenda) ?></strong> <span class="badge text-bg-secondary"><?= e((string) count($fretes)) ?> rota(s)</span></div>
    <div class="card-body">
        <form method="post" action="<?= e(base_url('cte/fretes')) ?>" class="row g-2 align-items-end mb-3" id="formFrete">
            <?= csrf_field() ?>
            <input type="hidden" name="revenda" value="<?= e($revenda) ?>">
            <div class="col-md-6">
                <label for="freteCidades" class="form-label small mb-0">Cidades (na ordem do 03.11.49.02, separadas por /)</label>
                <input type="text" class="form-control form-control-sm text-uppercase" id="freteCidades" name="cidades" maxlength="255" required placeholder="NOVA SERRANA / IGARATINGA">
            </div>
            <div class="col-6 col-md-2">
                <label for="freteValor" class="form-label small mb-0">Valor do frete</label>
                <input type="text" class="form-control form-control-sm" id="freteValor" name="valor" required inputmode="decimal" placeholder="1303,56">
            </div>
            <div class="col-6 col-md-2">
                <label for="freteCodigo" class="form-label small mb-0">Código</label>
                <input type="text" class="form-control form-control-sm" id="freteCodigo" name="codigo" maxlength="20" required placeholder="2743">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-check2"></i> Salvar frete</button>
            </div>
            <div class="col-12 form-text mt-0">Se as cidades já existirem, o valor e o código são atualizados.</div>
        </form>

        <?php if ($semFrete): ?>
            <div class="mb-3 small">
                Sem frete hoje:
                <?php foreach ($semFrete as $cidades): ?>
                    <button type="button" class="btn btn-sm btn-outline-warning mb-1" data-frete-cidades="<?= e($cidades) ?>"><?= e($cidades) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($fretes): ?>
            <input type="search" class="form-control form-control-sm mb-2" placeholder="Filtrar cidades..." data-filtro-fretes>
            <div class="table-responsive" style="max-height: 24rem">
                <table class="table table-sm table-striped mb-0">
                    <thead class="sticky-top bg-body">
                        <tr><th>Cidades</th><th class="text-end">Valor</th><th>Código</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fretes as $f): ?>
                            <tr data-frete="<?= e($f['cidades']) ?>">
                                <td><?= e($f['cidades']) ?></td>
                                <td class="text-end"><?= e(number_format((float) $f['valor'], 2, ',', '.')) ?></td>
                                <td><?= e($f['codigo']) ?></td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-link p-0 me-2" title="Editar"
                                            data-frete-cidades="<?= e($f['cidades']) ?>" data-frete-valor="<?= e(number_format((float) $f['valor'], 2, ',', '')) ?>" data-frete-codigo="<?= e($f['codigo']) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" action="<?= e(base_url('cte/fretes/' . $f['id'] . '/excluir')) ?>" class="d-inline"
                                          data-confirmar="Excluir o frete de <?= e($f['cidades']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="revenda" value="<?= e($revenda) ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Excluir"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted small mb-0">Nenhum frete cadastrado para <?= e($revenda) ?>.</p>
        <?php endif; ?>
    </div>
</div>

<script src="<?= e(asset('js/cte.js')) ?>" defer></script>
