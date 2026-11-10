<?php
// Rótulo => largura (%): colunas de texto mais largas, contagens mais estreitas (cabe em A4 retrato).
$colunas = [
    'Mapas' => 12, 'Inicial' => 11, 'Final' => 11, 'Emitidas' => 9, 'Placas' => 12,
    'KM planejado' => 10, 'Boleto' => 8, 'Cartão crédito' => 9, 'Pix' => 7, 'Tempo' => 11,
];
$valor = fn ($v) => $v === null || $v === '' ? '' : (string) $v;

// Imagem para o WhatsApp com os mapas que têm cartão de crédito: só mapa e placa,
// uma linha por mapa (desenhada em public/assets/js/farol.js).
$imagem = null;
if ($tirinhas && $cartao !== null) {
    $tituloCartao = 'Cartão de crédito - ' . $revenda . ' ' . \App\Helpers\Revenda::nome((string) $revenda);
    $mapasCartao = array_column($cartao, 'placa', 'mapa');
    $imagem = [
        'titulo' => $tituloCartao,
        'data' => $dataEntrega ? 'Entrega ' . fmt_data($dataEntrega) : '',
        'gerado' => 'Gerado em ' . date('d/m/Y H:i'),
        'logo' => asset('img/logo-cobeb.png'),
        'revendas' => [],
        'resumo' => [],
        'buffers' => ['CARTAO' => [
            'titulo' => $tituloCartao,
            'arquivo' => 'cartao-credito-' . strtolower((string) $revenda) . '-' . ($dataEntrega ?: date('Y-m-d')) . '.png',
            'colunas' => [['Mapa', 0.5, 'left'], ['Placa', 0.5, 'left']],
            'pedidos' => array_map(fn ($mapa, $placa) => [(string) $mapa, (string) $placa], array_keys($mapasCartao), $mapasCartao),
            'total' => $mapasCartao
                ? count($mapasCartao) . ' mapa(s) com cartão de crédito'
                : 'Nenhum mapa com cartão de crédito',
        ]],
    ];
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3 d-print-none">
    <div>
        <h1 class="h3 mb-1">Tirinha</h1>
        <p class="text-muted mb-0">Tirinhas dos mapas para os motoristas: notas, placa, KM, formas de pagamento e tempo previsto</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
    <?php if ($tirinhas): ?>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer"></i> Imprimir <?= e((string) count($tirinhas)) ?> tirinha(s) de <?= e((string) $revenda) ?>
        </button>
    <?php endif; ?>
    <?php if ($imagem): ?>
        <button type="button" class="btn btn-success" data-imagem-buffer="CARTAO">
            <i class="bi bi-whatsapp"></i> Imagem do cartão de crédito (<?= e((string) count($mapasCartao)) ?> mapa(s))
        </button>
        <script type="application/json" id="dadosImagemFarol"><?= json_encode($imagem, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
        <script src="<?= e(asset('js/farol.js')) ?>" defer></script>
    <?php endif; ?>
    </div>
</div>

<?php if ($tirinhas): ?>
    <?php foreach ($avisos as $aviso): ?>
        <div class="alert alert-warning py-2 small d-print-none"><?= e($aviso) ?></div>
    <?php endforeach; ?>

    <div class="tirinhas mb-4">
        <?php foreach ($tirinhas as $t): ?>
            <table class="tirinha">
                <colgroup>
                    <?php foreach ($colunas as $largura): ?>
                        <col style="width: <?= e((string) $largura) ?>%">
                    <?php endforeach; ?>
                </colgroup>
                <thead>
                    <tr>
                        <?php foreach (array_keys($colunas) as $coluna): ?>
                            <th><?= e($coluna) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-bold"><?= e($t['mapa']) ?></td>
                        <td><?= e($valor($t['inicial'])) ?></td>
                        <td><?= e($valor($t['final'])) ?></td>
                        <td><?= e($valor($t['emitidas'])) ?></td>
                        <td><?= e($t['placa']) ?></td>
                        <td><?= e(number_format($t['km'], 2, ',', '.')) ?></td>
                        <td><?= e($valor($t['boleto'])) ?></td>
                        <td><?= e($valor($t['cartao'])) ?></td>
                        <td><?= e($valor($t['pix'])) ?></td>
                        <td><?= e($t['tempo']) ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3 d-print-none">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <form method="post" action="<?= e(base_url('tirinha/gerar')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label d-block">Revenda</label>
                        <?php foreach ($revendas as $sigla): ?>
                            <input type="radio" class="btn-check" name="revenda" id="revenda<?= e($sigla) ?>" value="<?= e($sigla) ?>"
                                   <?= $sigla === $revenda ? 'checked' : '' ?> required>
                            <label class="btn btn-outline-primary" for="revenda<?= e($sigla) ?>"><?= e($sigla) ?></label>
                        <?php endforeach; ?>
                        <div class="form-text">Só os mapas desta revenda (coluna UNB do 03.11.49.02) viram tirinha.</div>
                    </div>

                    <div class="mb-3">
                        <label for="mapas" class="form-label">03.11.49.02: mapas (arquivo)</label>
                        <input type="file" class="form-control" id="mapas" name="mapas" accept=".xlsx,.xlsm,.csv,.inf,.txt" required>
                        <div class="form-text">O mesmo relatório que ia na aba "Promax" da planilha (Nro do Mapa, Placa, KM Prev., Tempo Prev.).</div>
                    </div>

                    <div class="mb-3">
                        <label for="notas" class="form-label">03.02.31: notas por mapa</label>
                        <textarea class="form-control font-monospace small" id="notas" name="notas" rows="6"
                                  placeholder="398891  112569  112569  1  0  0  0  1  0  1"><?= e($notas) ?></textarea>
                        <div class="form-text">Copie da tela do 03.02.31 e cole aqui, como na aba "03.02.31" da planilha.</div>
                    </div>

                    <div class="mb-3">
                        <label for="pagamentos" class="form-label">03.02.37 (um ou mais arquivos)</label>
                        <input type="file" class="form-control" id="pagamentos" name="pagamentos[]" accept=".inf,.csv,.txt" multiple>
                        <div class="form-text">Envie juntos o de boleto e o de condição de pagamento (segure Ctrl para marcar vários).</div>
                    </div>

                    <p class="small text-muted">Os arquivos precisam ser enviados de novo a cada geração.</p>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-scissors"></i> Gerar tirinhas
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><strong>Como a tirinha é montada</strong></div>
            <div class="card-body small">
                <ul class="mb-0">
                    <li>Uma tirinha por mapa, na ordem do 03.11.49.02.</li>
                    <li><strong>Placas, KM planejado e Tempo:</strong> do 03.11.49.02 (Placa, KM Prev. e Tempo Prev. (+almoço)).</li>
                    <li><strong>Inicial, Final e Emitidas:</strong> do 03.02.31 (nota inicial, nota final e a 8ª coluna a partir do mapa).</li>
                    <li><strong>Boleto:</strong> notas do mapa com "Nr Tit. Bco" preenchido no 03.02.37.</li>
                    <li><strong>Cartão crédito:</strong> notas do mapa com Cond. pagt <?= e(\App\Helpers\Tirinha::COND_CARTAO) ?>. <strong>Pix:</strong> Cond. pagt <?= e(\App\Helpers\Tirinha::COND_PIX) ?>.</li>
                    <li>Uma nota que aparece em mais de um arquivo conta uma vez. Mapa sem a forma de pagamento = 0.</li>
                    <li>As tirinhas não são gravadas; o 03.11.49.02 também atualiza a aba CTE da revenda.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
