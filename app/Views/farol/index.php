<?php

use App\Helpers\Revenda;

$fmtInt = fn ($v) => number_format((float) $v, 0, ',', '.');
$fmtCx = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.');
// Quantidade ajustada na tela: mostra a do 03.11.49.02 ao lado.
$ajustado = fn (array $f, string $campo) => isset($f['calculados'][$campo])
    ? ' <span class="badge text-bg-warning" title="Ajustado na tela">ajustado</span><div class="small text-muted">arquivo: ' . e($fmtInt($f['calculados'][$campo])) . '</div>'
    : '';
// Setor do pedido do buffer: "245 - WESLEI RODRIGUES".
$fmtSetor = fn (array $p) => implode(' - ', array_filter([(string) ($p['setor_codigo'] ?? ''), (string) ($p['setor_descricao'] ?? '')], 'strlen'));

// Dados já formatados para a imagem de envio no WhatsApp (desenhada em public/assets/js/farol.js).
$datasEntrega = array_filter(array_column($farois, 'data_entrega'));
$imagem = [
    'titulo' => 'Farol de Roteirização',
    'data' => $datasEntrega ? 'Entrega ' . fmt_data(max($datasEntrega)) : '',
    'gerado' => 'Gerado em ' . date('d/m/Y H:i'),
    'logo' => asset('img/logo-cobeb.png'),
    'revendas' => [],
    'resumo' => [],
    'buffers' => [],
];
foreach ($farois as $revenda => $f) {
    $imagem['buffers'][$revenda] = [
        'titulo' => 'Buffer reprogramado - ' . $revenda . ' ' . Revenda::nome($revenda),
        // Uma linha por cliente (pedidos duplicados somados); na imagem vai a quantidade de pedidos.
        'pedidos' => array_map(fn ($p) => [
            (string) $p['cliente_codigo'],
            (string) ($p['nome_fantasia'] ?? ''),
            $fmtSetor($p),
            (string) $p['pedidos'],
            fmt_hl($p['volume_hl']),
        ], $f['buffer']),
        'total' => $f['clientes_buffer'] . ' cliente(s), ' . array_sum(array_column($f['buffer'], 'pedidos')) . ' pedido(s), ' . fmt_hl($f['volume_buffer']) . ' HL',
    ];
    $imagem['revendas'][] = [
        'titulo' => $revenda . ' - ' . Revenda::nome($revenda),
        'linhas' => [
            ['Volume vendido (HL)', $f['volume_vendido'] === null ? '—' : fmt_hl($f['volume_vendido']), ''],
            ['Volume roteirizado (HL)', $f['volume_roteirizado'] === null ? '—' : fmt_hl($f['volume_roteirizado']), ''],
            ['Volume buffer (HL)', fmt_hl($f['volume_buffer']), ''],
            ['Clientes roteirizados', $fmtInt($f['clientes_roteirizados']), ''],
            ['Clientes buffer', $fmtInt($f['clientes_buffer']), ''],
            ['Peso roteirizado (kg)', $fmtInt($f['peso_roteirizado']), ''],
            ['Mapas FF', $fmtInt($f['mapas_ff']), ''],
            ['Mapas Spot', $fmtInt($f['mapas_spot']), (string) ($f['digitados']['observacao_spot'] ?? '')],
            ['Recarga programada', $fmtInt($f['recargas']), (string) ($f['digitados']['observacao_recarga'] ?? '')],
            ['Cx/viagem FF', $fmtCx($f['cx_viagem_ff']), ''],
            ['Cx/viagem Spot', $fmtCx($f['cx_viagem_spot']), ''],
        ],
    ];
}
if (count($farois) > 1) {
    $imagem['resumo'] = [
        ['Vendido (HL)', fmt_hl($resumo['volume_vendido'])],
        ['Roteirizado (HL)', fmt_hl($resumo['volume_roteirizado'])],
        ['Buffer (HL)', fmt_hl($resumo['volume_buffer'])],
        ['Clientes roteirizados', $fmtInt($resumo['clientes_roteirizados'])],
        ['Clientes buffer', $fmtInt($resumo['clientes_buffer'])],
    ];
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">Farol</h1>
        <p class="text-muted mb-0">Painel do dia por revenda: volume, buffer, clientes e mapas roteirizados</p>
    </div>
    <button type="button" class="btn btn-success" id="baixarImagemFarol">
        <i class="bi bi-whatsapp"></i> Baixar imagem para WhatsApp
    </button>
</div>

<script type="application/json" id="dadosImagemFarol"><?= json_encode($imagem, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= e(asset('js/farol.js')) ?>" defer></script>

<div class="row g-3 mb-4">
    <?php foreach ($farois as $revenda => $f): ?>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><span class="badge badge-revenda"><?= e($revenda) ?></span> <?= e(Revenda::nome($revenda)) ?></strong>
                    <span class="text-muted small">
                        <?= $f['data_entrega'] ? 'Entrega ' . e(fmt_data($f['data_entrega'])) : 'Sem mapas importados' ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <th class="ps-3">Volume vendido (HL)</th>
                                <td class="text-end pe-3"><?= $f['volume_vendido'] === null ? '<span class="text-muted">importar</span>' : e(fmt_hl($f['volume_vendido'])) ?></td>
                            </tr>
                            <tr>
                                <th class="ps-3">Volume roteirizado (HL)</th>
                                <td class="text-end pe-3"><?= $f['volume_roteirizado'] === null ? '—' : e(fmt_hl($f['volume_roteirizado'])) ?></td>
                            </tr>
                            <tr>
                                <th class="ps-3">Volume buffer (HL)</th>
                                <td class="text-end pe-3"><?= $f['buffer_importado'] ? e(fmt_hl($f['volume_buffer'])) : '<span class="text-muted">importar</span>' ?></td>
                            </tr>
                            <tr>
                                <th class="ps-3">Clientes roteirizados</th>
                                <td class="text-end pe-3"><?= e($fmtInt($f['clientes_roteirizados'])) ?></td>
                            </tr>
                            <tr>
                                <th class="ps-3">Clientes buffer</th>
                                <td class="text-end pe-3"><?= e($fmtInt($f['clientes_buffer'])) ?></td>
                            </tr>
                            <tr>
                                <th class="ps-3">Peso roteirizado (kg)</th>
                                <td class="text-end pe-3"><?= e($fmtInt($f['peso_roteirizado'])) ?></td>
                            </tr>
                            <tr>
                                <th class="ps-3">Mapas FF roteirizados</th>
                                <td class="text-end pe-3"><?= e($fmtInt($f['mapas_ff'])) ?><?= $ajustado($f, 'mapas_ff') ?></td>
                            </tr>
                            <tr>
                                <th class="ps-3">Mapas Spot roteirizados</th>
                                <td class="text-end pe-3">
                                    <?= e($fmtInt($f['mapas_spot'])) ?><?= $ajustado($f, 'mapas_spot') ?>
                                    <?php if (!empty($f['digitados']['observacao_spot'])): ?>
                                        <div class="small text-muted"><?= e($f['digitados']['observacao_spot']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-3">Recarga programada</th>
                                <td class="text-end pe-3">
                                    <?= e($fmtInt($f['recargas'])) ?><?= $ajustado($f, 'recargas') ?>
                                    <?php if (!empty($f['digitados']['observacao_recarga'])): ?>
                                        <div class="small text-muted"><?= e($f['digitados']['observacao_recarga']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-3">Cx/viagem FF</th>
                                <td class="text-end pe-3"><?= e($fmtCx($f['cx_viagem_ff'])) ?></td>
                            </tr>
                            <tr>
                                <th class="ps-3">Cx/viagem Spot</th>
                                <td class="text-end pe-3"><?= e($fmtCx($f['cx_viagem_spot'])) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <form method="post" action="<?= e(base_url('farol/dados')) ?>" class="row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="revenda" value="<?= e($revenda) ?>">
                        <?php foreach (['mapas_ff' => 'Mapas FF', 'mapas_spot' => 'Mapas Spot', 'recargas' => 'Recarga'] as $campo => $rotulo): ?>
                            <div class="col-4">
                                <label class="form-label small text-muted mb-0" for="<?= e($campo . '_' . $revenda) ?>"><?= e($rotulo) ?></label>
                                <input type="number" min="0" max="999" step="1" class="form-control form-control-sm"
                                       id="<?= e($campo . '_' . $revenda) ?>" name="<?= e($campo) ?>"
                                       placeholder="<?= e((string) ($f['calculados'][$campo] ?? $f[$campo])) ?>"
                                       value="<?= e(isset($f['calculados'][$campo]) ? (string) $f[$campo] : '') ?>"
                                       title="Em branco = quantidade do 03.11.49.02 (<?= e((string) ($f['calculados'][$campo] ?? $f[$campo])) ?>)">
                            </div>
                        <?php endforeach; ?>
                        <div class="col-6">
                            <input type="text" maxlength="150" class="form-control form-control-sm" name="observacao_spot" placeholder="Obs. Spot (ex.: 1 VAN)"
                                   value="<?= e($f['digitados']['observacao_spot'] ?? '') ?>">
                        </div>
                        <div class="col-6">
                            <input type="text" maxlength="150" class="form-control form-control-sm" name="observacao_recarga" placeholder="Obs. recarga"
                                   value="<?= e($f['digitados']['observacao_recarga'] ?? '') ?>">
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center">
                            <span class="small text-muted">
                                <?php if ($f['digitados']): ?>
                                    <?= e($f['digitados']['usuario_nome']) ?>, <?= e(fmt_data_hora($f['digitados']['atualizado_em'])) ?>
                                <?php endif; ?>
                            </span>
                            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check2"></i> Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (count($farois) > 1): ?>
    <div class="card mb-4">
        <div class="card-header"><strong>Resumo do dia</strong></div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-6 col-md"><div class="text-muted small">Volume total vendido (HL)</div><div class="fs-5"><?= e(fmt_hl($resumo['volume_vendido'])) ?></div></div>
                <div class="col-6 col-md"><div class="text-muted small">Volume total roteirizado (HL)</div><div class="fs-5"><?= e(fmt_hl($resumo['volume_roteirizado'])) ?></div></div>
                <div class="col-6 col-md"><div class="text-muted small">Volume total buffer (HL)</div><div class="fs-5"><?= e(fmt_hl($resumo['volume_buffer'])) ?></div></div>
                <div class="col-6 col-md"><div class="text-muted small">Clientes roteirizados</div><div class="fs-5"><?= e($fmtInt($resumo['clientes_roteirizados'])) ?></div></div>
                <div class="col-6 col-md"><div class="text-muted small">Clientes buffer</div><div class="fs-5"><?= e($fmtInt($resumo['clientes_buffer'])) ?></div></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><strong>Importar relatórios do Farol</strong></div>
            <div class="card-body">
                <form method="post" action="<?= e(base_url('importacoes')) ?>" enctype="multipart/form-data" data-loading>
                    <?= csrf_field() ?>
                    <input type="hidden" name="voltar" value="farol">

                    <div class="mb-3">
                        <label for="tipoFarol" class="form-label">Relatório</label>
                        <select class="form-select" id="tipoFarol" name="tipo" required>
                            <?php foreach ($tiposImportacao as $valor => $descricao): ?>
                                <option value="<?= e($valor) ?>"><?= e($descricao) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php require dirname(__DIR__) . '/partials/campo-revenda.php'; ?>

                    <div class="mb-3">
                        <label for="arquivoFarol" class="form-label">Arquivo</label>
                        <input type="file" class="form-control" id="arquivoFarol" name="arquivo" accept=".xlsx,.csv,.inf" required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                        <i class="bi bi-upload"></i> Importar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><strong>Como o Farol é calculado</strong></div>
            <div class="card-body small">
                <ul class="mb-0">
                    <li><strong>Mapas (03.11.49.02):</strong> placa repetida: o 1º mapa da placa (na ordem do arquivo) segue a regra normal e os seguintes (2º, 3º, 4º...) são recarga. Placa começando com REC = recarga; carga "Freteiro" = Spot; os demais = FF.</li>
                    <li><strong>Clientes roteirizados:</strong> soma das entregas de todos os mapas (FF + Spot + recarga). <strong>Peso:</strong> soma do total de peso.</li>
                    <li><strong>Cx/viagem:</strong> total de caixas do tipo ÷ quantidade de mapas do tipo.</li>
                    <li><strong>Ajuste das quantidades:</strong> Mapas FF, Mapas Spot e Recarga podem ser trocados no rodapé do cartão (em branco = quantidade do 03.11.49.02). O ajuste vale só para a entrega dos mapas importados quando foi salvo e entra na imagem do WhatsApp; Cx/viagem continua pelo arquivo.</li>
                    <li><strong>Volumes (Cora, pedidos_roteirizacao):</strong> soma do HectoLitro (coluna W) pela Situação do pedido (coluna K). <strong>Roteirizado:</strong> ENVIADO_HUB_FATURAMENTO + AGUARDANDO_ROTEIRIZACAO. <strong>Buffer:</strong> REPROGRAMADO. <strong>Vendido:</strong> as três juntas (CANCELADO não entra).</li>
                    <li><strong>Clientes buffer:</strong> clientes distintos dos pedidos REPROGRAMADO. O setor vem da base de clientes (ou da última importação do Cora da Crítica).</li>
                    <li>Cada importação substitui os dados da revenda; o histórico fica em Importações.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php foreach ($farois as $revenda => $f): ?>
    <?php if (array_sum(array_column($f['grupos'], 'quantidade')) === 0 && !$f['buffer']) {
        continue;
    } ?>
    <div class="card mb-3">
        <div class="card-header">
            <a class="text-decoration-none" data-bs-toggle="collapse" href="#detalhe-<?= e($revenda) ?>" role="button" aria-expanded="false">
                <i class="bi bi-chevron-down"></i> <strong>Mapas e buffer &mdash; <?= e($revenda) ?> <?= e(Revenda::nome($revenda)) ?></strong>
            </a>
        </div>
        <div class="collapse" id="detalhe-<?= e($revenda) ?>">
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($tipos as $tipo => $rotulo): ?>
                        <?php $g = $f['grupos'][$tipo]; ?>
                        <div class="col-xl-4">
                            <h2 class="h6"><?= e($rotulo) ?> <span class="badge text-bg-secondary"><?= e((string) $g['quantidade']) ?></span></h2>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr><th>Placa</th><th>Veículo</th><th class="text-end">Cx/viagem</th><th class="text-end">Pedidos</th><th class="text-end">Peso</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($g['mapas'] as $mapa): ?>
                                            <tr>
                                                <td><?= e($mapa['placa']) ?></td>
                                                <td><?= e($mapa['veiculo'] ?? '') ?></td>
                                                <td class="text-end"><?= e($fmtCx($mapa['caixas'])) ?></td>
                                                <td class="text-end"><?= e($fmtInt($mapa['entregas'])) ?></td>
                                                <td class="text-end"><?= e($fmtInt($mapa['peso'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="fw-semibold">
                                            <td colspan="2">Total</td>
                                            <td class="text-end"><?= e($fmtCx($g['caixas'])) ?></td>
                                            <td class="text-end"><?= e($fmtInt($g['entregas'])) ?></td>
                                            <td class="text-end"><?= e($fmtInt($g['peso'])) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-2">
                    <h2 class="h6 mb-0">Buffer reprogramado <span class="badge text-bg-secondary"><?= e((string) count($f['buffer'])) ?> cliente(s)</span></h2>
                    <?php if ($f['buffer']): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-success" data-imagem-buffer="<?= e($revenda) ?>">
                                <i class="bi bi-whatsapp"></i> Baixar imagem do buffer
                            </button>
                            <a class="btn btn-sm btn-outline-success" href="<?= e(base_url('farol/buffer/' . $revenda)) ?>">
                                <i class="bi bi-file-earmark-excel"></i> Baixar em Excel
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($f['buffer']): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr><th>Cliente</th><th>Nome do cliente</th><th>Setor</th><th>Pedidos</th><th class="text-end">Volume total (HL)</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($f['buffer'] as $pedido): ?>
                                    <tr>
                                        <td><?= e((string) $pedido['cliente_codigo']) ?></td>
                                        <td><?= e($pedido['nome_fantasia'] ?? '') ?></td>
                                        <td><?= e($fmtSetor($pedido)) ?></td>
                                        <td class="small"><?= e($pedido['numero_pedido']) ?></td>
                                        <td class="text-end"><?= e(fmt_hl($pedido['volume_hl'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">Nenhum pedido reprogramado.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
