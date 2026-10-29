<?php
$abaFaturado = !$buscaTrocas && isset($_GET['faturado']);
$abaNotas = !$buscaTrocas && !$abaFaturado && (isset($_GET['notas']) || $setor !== null);
$rsHl = fn (?float $valor): string => $valor === null ? '—' : number_format($valor, 4, ',', '.');
$descricaoImportacao = function (string $tipo) use ($importacoes): string {
    $partes = [];
    foreach ($importacoes[$tipo] ?? [] as $sigla => $imp) {
        $partes[] = $sigla . ' ' . fmt_data_hora($imp['criado_em']);
    }
    return $partes ? 'Atualizado: ' . implode(' · ', $partes) . '.' : 'Relatório ainda não importado.';
};
?>
<div class="mb-3">
    <h1 class="h3 mb-1">Troca</h1>
    <p class="text-muted mb-0">
        <?= $revenda !== null ? 'Revenda ' . e($revenda) : 'Todas as revendas' ?><?= $mesa !== null ? ' &middot; Sala ' . e((string) $mesa) : '' ?>
    </p>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $abaNotas || $abaFaturado ? '' : 'active' ?>" data-bs-toggle="tab" data-bs-target="#painelSolicitacoes" type="button" role="tab">
            <i class="bi bi-search"></i> Solicitações por cliente
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $abaNotas ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#painelNotas" type="button" role="tab">
            <i class="bi bi-receipt"></i> Notas em rota <span class="badge text-bg-light"><?= e((string) count(array_unique(array_map(fn ($n) => $n['revenda'] . $n['nota'] . '-' . $n['serie'], $notas)))) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $abaFaturado ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#painelFaturado" type="button" role="tab">
            <i class="bi bi-cash-coin"></i> Faturado (aprovado)
        </button>
    </li>
    <?php if ($podeImportar): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#painelImportar" type="button" role="tab">
                <i class="bi bi-upload"></i> Importar relatórios
            </button>
        </li>
    <?php endif; ?>
</ul>

<div class="tab-content">
    <!-- Solicitações de troca (03.18.05) -->
    <div class="tab-pane fade <?= $abaNotas || $abaFaturado ? '' : 'show active' ?>" id="painelSolicitacoes" role="tabpanel">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
            <p class="small text-muted mb-0">Solicitações do Promax 03.18.05. <?= e($descricaoImportacao('trocas')) ?></p>
            <form method="get" action="<?= e(base_url('troca')) ?>" class="d-flex flex-wrap gap-2 align-items-end">
                <div>
                    <label for="cliente" class="form-label small mb-1">Código Cliente</label>
                    <input type="number" class="form-control form-control-sm" id="cliente" name="cliente" min="1" value="<?= e((string) ($cliente ?? '')) ?>" <?= $abaNotas || $abaFaturado ? '' : 'autofocus' ?>>
                </div>
                <div>
                    <label for="filtroSetorTroca" class="form-label small mb-1">Setor</label>
                    <select class="form-select form-select-sm" id="filtroSetorTroca" name="setor_troca">
                        <option value="">Todos</option>
                        <?php foreach ($opcoesTroca['setores'] as $s): ?>
                            <option value="<?= e((string) $s) ?>" <?= $s === $setorTroca ? 'selected' : '' ?>>Setor <?= e((string) $s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filtroSituacao" class="form-label small mb-1">Situação</label>
                    <select class="form-select form-select-sm" id="filtroSituacao" name="situacao">
                        <option value="">Todas</option>
                        <?php foreach ($opcoesTroca['status'] as $st): ?>
                            <option value="<?= e($st) ?>" <?= $st === $situacao ? 'selected' : '' ?>><?= e($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filtroDataInicio" class="form-label small mb-1">De</label>
                    <input type="date" class="form-control form-control-sm" id="filtroDataInicio" name="data_inicio" value="<?= e((string) ($dataInicio ?? '')) ?>">
                </div>
                <div>
                    <label for="filtroDataFim" class="form-label small mb-1">Até</label>
                    <input type="date" class="form-control form-control-sm" id="filtroDataFim" name="data_fim" value="<?= e((string) ($dataFim ?? '')) ?>">
                </div>
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Consultar</button>
                <?php if ($buscaTrocas): ?>
                    <a href="<?= e(base_url('troca')) ?>" class="btn btn-sm btn-outline-secondary">Limpar</a>
                <?php endif; ?>
            </form>
        </div>

        <?php
        $descricaoFiltro = implode(' · ', array_filter([
            $cliente !== null ? 'Cliente ' . $cliente : null,
            $setorTroca !== null ? 'Setor ' . $setorTroca : null,
            $situacao !== null ? 'Situação ' . $situacao : null,
            match (true) {
                $dataInicio !== null && $dataFim !== null => 'De ' . fmt_data($dataInicio) . ' até ' . fmt_data($dataFim),
                $dataInicio !== null => 'A partir de ' . fmt_data($dataInicio),
                $dataFim !== null => 'Até ' . fmt_data($dataFim),
                default => null,
            },
        ]));
        ?>
        <?php if (!$buscaTrocas): ?>
            <div class="text-muted">Informe o código do cliente, o setor, a situação e/ou o período para ver as solicitações de troca.</div>
        <?php elseif (!$trocas): ?>
            <div class="alert alert-warning">
                Nenhuma solicitação de troca encontrada (<?= e($descricaoFiltro) ?>)<?= $mesa !== null ? ' na Sala ' . e((string) $mesa) : '' ?>.
            </div>
        <?php else: ?>
            <?php if ($trocasCortadas): ?>
                <div class="alert alert-warning small py-2">Mostrando os <?= e((string) count($trocas)) ?> itens mais recentes. Use mais filtros para ver o restante.</div>
            <?php endif; ?>
            <div class="card">
                <div class="card-header bg-white">
                    <span class="fw-semibold">
                        <?php if ($cliente !== null): ?>
                            <?= e((string) $cliente) ?> &middot; <?= e($trocas[0]['cliente_nome']) ?>
                        <?php else: ?>
                            <?= e($descricaoFiltro) ?>
                        <?php endif; ?>
                    </span>
                    <?php if ($cliente !== null && $descricaoFiltro !== 'Cliente ' . $cliente): ?>
                        <span class="small text-muted"><?= e($descricaoFiltro) ?></span>
                    <?php endif; ?>
                    <span class="badge text-bg-light ms-1"><?= e((string) count($trocas)) ?> item(ns)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <?php if ($revenda === null): ?><th>Revenda</th><?php endif; ?>
                                <th>Setor</th><?php if ($cliente === null): ?><th>Código Cliente</th><?php endif; ?>
                                <th>Nome Cliente</th><th>Data Solicitação</th><th>Status Solicitação</th><th>Nota Fiscal/Serie</th>
                                <th>Produto</th><th>Descrição Produto</th><th class="text-end">Quantidade</th><th>U.M.</th>
                                <th>Sistema Origem</th><th>Observação</th><th>Justificativa da Logística</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trocas as $t): ?>
                                <?php $corStatus = ['aprovada' => 'success', 'reprovada' => 'danger', 'pendente' => 'warning'][mb_strtolower((string) $t['status_solicitacao'])] ?? 'secondary'; ?>
                                <tr>
                                    <?php if ($revenda === null): ?><td><span class="badge badge-revenda"><?= e($t['revenda']) ?></span></td><?php endif; ?>
                                    <td><?= e((string) ($t['setor'] ?? '—')) ?></td>
                                    <?php if ($cliente === null): ?><td><?= e((string) $t['cliente_codigo']) ?></td><?php endif; ?>
                                    <td><?= e($t['cliente_nome']) ?></td>
                                    <td class="text-nowrap"><?= e(fmt_data($t['data_solicitacao'])) ?></td>
                                    <td><span class="badge text-bg-<?= e($corStatus) ?>"><?= e($t['status_solicitacao']) ?></span></td>
                                    <td class="text-nowrap"><?= e($t['nota_fiscal']) ?></td>
                                    <td><?= e($t['produto_codigo']) ?></td>
                                    <td><?= e($t['produto_descricao']) ?></td>
                                    <td class="text-end"><?= e(number_format((float) $t['quantidade'], 0, ',', '.')) ?></td>
                                    <td><?= e($t['unidade']) ?></td>
                                    <td><?= e($t['sistema_origem']) ?></td>
                                    <td class="small"><?= e($t['observacao']) ?></td>
                                    <td class="small">
                                        <?php if ($t['justificativa']): ?>
                                            <span class="text-danger-emphasis fw-semibold"><i class="bi bi-chat-left-text"></i> <?= e($t['justificativa']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Notas de troca em rota (03.02.37) -->
    <div class="tab-pane fade <?= $abaNotas ? 'show active' : '' ?>" id="painelNotas" role="tabpanel">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
            <p class="small text-muted mb-0">Notas do Promax 03.02.37 para a rota do dia. <?= e($descricaoImportacao('notas_troca')) ?></p>
            <form method="get" action="<?= e(base_url('troca')) ?>" class="d-flex gap-2 align-items-end">
                <input type="hidden" name="notas" value="1">
                <?php if ($mesa === null): ?>
                    <div>
                        <label for="filtroMesaNota" class="form-label small mb-1">Sala</label>
                        <select class="form-select form-select-sm" id="filtroMesaNota" name="mesa" data-autosubmit data-limpa="setor">
                            <option value="">Todas</option>
                            <?php foreach ($mesas as $m): ?>
                                <option value="<?= e((string) $m) ?>" <?= $m === $mesaNotas ? 'selected' : '' ?>>Sala <?= e((string) $m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div>
                    <label for="filtroSetorNota" class="form-label small mb-1">Setor</label>
                    <select class="form-select form-select-sm" id="filtroSetorNota" name="setor" data-autosubmit>
                        <option value="">Todos</option>
                        <?php foreach ($setoresComNota as $s): ?>
                            <option value="<?= e((string) $s) ?>" <?= $s === $setor ? 'selected' : '' ?>>Setor <?= e((string) $s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Filtrar</button></noscript>
                <?php if ($notas): ?>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalFotoNotas" title="Gera uma imagem das notas do filtro atual para enviar pelo WhatsApp">
                        <i class="bi bi-whatsapp"></i> Imagem para WhatsApp
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($notas): ?>
            <!-- Dados do modelo de imagem (desenhado em app.js; texto escapado pelo json_encode). -->
            <script type="application/json" id="dadosFotoNotas"><?= json_encode($fotoNotas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

            <div class="modal fade" id="modalFotoNotas" tabindex="-1" aria-labelledby="tituloFotoNotas" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="tituloFotoNotas"><i class="bi bi-whatsapp text-success"></i> Imagem para WhatsApp</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body text-center bg-light">
                            <img id="previaFotoNotas" alt="Prévia da imagem das notas de troca" class="img-fluid border shadow-sm" style="max-width: 540px">
                        </div>
                        <div class="modal-footer">
                            <span class="small text-muted me-auto" id="avisoFotoNotas">No celular, "Compartilhar" abre direto o WhatsApp. No computador, baixe a imagem e anexe na conversa.</span>
                            <button type="button" class="btn btn-outline-primary" id="baixarFotoNotas"><i class="bi bi-download"></i> Baixar imagem</button>
                            <button type="button" class="btn btn-success" id="compartilharFotoNotas"><i class="bi bi-share"></i> Compartilhar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$notas): ?>
            <div class="alert alert-info">Nenhuma nota de troca em rota<?= $mesaNotas !== null ? ' na Sala ' . e((string) $mesaNotas) : '' ?><?= $setor !== null ? ' no setor ' . e((string) $setor) : '' ?>.</div>
        <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <?php if ($revenda === null): ?><th>Revenda</th><?php endif; ?>
                                <th>Setor</th><th>Nota/Série</th><th>Emissão</th><th>Operação</th><th>Cód. Cliente</th><th>Cliente</th>
                                <th>Produto</th><th>Descrição</th><th class="text-end">Qtde</th><th>Unidade</th><th class="text-end">Total</th><th>Mapa</th><th>Status NF-e</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $notaAnterior = null; ?>
                            <?php foreach ($notas as $n): ?>
                                <?php
                                $chaveNota = $n['revenda'] . $n['nota'] . '-' . $n['serie'];
                                $novaNota = $chaveNota !== $notaAnterior;
                                $notaAnterior = $chaveNota;
                                ?>
                                <tr class="<?= $novaNota ? 'border-top border-2' : '' ?>">
                                    <?php if ($revenda === null): ?><td><span class="badge badge-revenda"><?= e($n['revenda']) ?></span></td><?php endif; ?>
                                    <td><?= e((string) ($n['setor'] ?? '—')) ?></td>
                                    <td class="text-nowrap fw-semibold"><?= $novaNota ? e($n['nota'] . ' / ' . $n['serie']) : '' ?></td>
                                    <td class="text-nowrap"><?= e(fmt_data($n['data_emissao'])) ?></td>
                                    <td><?= e((string) $n['operacao']) ?></td>
                                    <td><?= e((string) $n['cliente_codigo']) ?></td>
                                    <td><?= e($n['cliente_nome']) ?></td>
                                    <td><?= e($n['produto_codigo']) ?></td>
                                    <td><?= e($n['produto_descricao']) ?></td>
                                    <td class="text-end"><?= e(number_format((float) $n['quantidade'], 0, ',', '.')) ?></td>
                                    <td><?= e($n['unidade']) ?></td>
                                    <td class="text-end text-nowrap"><?= e(fmt_moeda($n['valor_total'])) ?></td>
                                    <td class="text-nowrap"><?= e($n['mapa']) ?></td>
                                    <td class="small"><?= e($n['status_nfe']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Faturado (aprovado) da Verba de troca: espelho da aba Aprovação troca -->
    <div class="tab-pane fade <?= $abaFaturado ? 'show active' : '' ?>" id="painelFaturado" role="tabpanel">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
            <p class="small text-muted mb-0">
                Trocas aprovadas (03.18.05) contra a verba do setor: 10% do HL vendido (03.05.09). GAP = verba − Force aprovado.
            </p>
            <form method="get" action="<?= e(base_url('troca')) ?>" class="d-flex gap-2 align-items-end">
                <input type="hidden" name="faturado" value="1">
                <?php if (count($revendasMenu) > 1): ?>
                    <div>
                        <label for="filtroRevendaFaturado" class="form-label small mb-1">Revenda</label>
                        <select class="form-select form-select-sm" id="filtroRevendaFaturado" name="revenda" data-autosubmit data-limpa="mesa">
                            <option value="">Todas</option>
                            <?php foreach ($revendasMenu as $sigla): ?>
                                <option value="<?= e($sigla) ?>" <?= $sigla === $revenda ? 'selected' : '' ?>><?= e($sigla) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <?php if ($mesa === null): ?>
                    <div>
                        <label for="filtroMesaFaturado" class="form-label small mb-1">Sala</label>
                        <select class="form-select form-select-sm" id="filtroMesaFaturado" name="mesa" data-autosubmit>
                            <option value="">Todas</option>
                            <?php foreach ($mesas as $m): ?>
                                <option value="<?= e((string) $m) ?>" <?= $m === $mesaNotas ? 'selected' : '' ?>>Sala <?= e((string) $m) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Filtrar</button></noscript>
            </form>
        </div>

        <?php if (!$verbas): ?>
            <div class="alert alert-info">Nenhum setor cadastrado<?= $mesaNotas !== null ? ' na Sala ' . e((string) $mesaNotas) : '' ?><?= $revenda !== null ? ' na revenda ' . e($revenda) : '' ?>.</div>
        <?php endif; ?>

        <?php foreach ($verbas as $sigla => $v): ?>
            <?php $t = $v['totais']; ?>
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">
                    <span class="badge badge-revenda"><?= e($sigla) ?></span>
                    Faturado (aprovado)<?= $mesaNotas !== null ? ' &middot; Sala ' . e((string) $mesaNotas) : '' ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th>Setor (RN)</th>
                                <th class="text-end">Promax</th>
                                <th class="text-end">Force</th>
                                <th class="text-end" title="Volume HL x 10%">Verba</th>
                                <th class="text-end" title="Verba - Force aprovado">GAP</th>
                                <th class="text-end" title="Quantidade Hecto do 03.05.09">Volume (HL)</th>
                                <th class="text-end">R$/HL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($v['setores'] as $s): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e((string) $s['setor']) ?></td>
                                    <td class="text-end"><?= e(fmt_moeda($s['aprovada_promax'])) ?></td>
                                    <td class="text-end"><?= e(fmt_moeda($s['aprovada_force'])) ?></td>
                                    <td class="text-end"><?= e(fmt_moeda($s['verba'])) ?></td>
                                    <td class="text-end"><span class="badge text-bg-<?= e(cor_gap($s['gap'])) ?>"><?= e(fmt_moeda($s['gap'])) ?></span></td>
                                    <td class="text-end"><?= e(fmt_hl($s['volume'])) ?></td>
                                    <td class="text-end"><?= e($rsHl($s['aprovada_rs_hl'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td>Total</td>
                                <td class="text-end"><?= e(fmt_moeda($t['aprovada_promax'])) ?></td>
                                <td class="text-end"><?= e(fmt_moeda($t['aprovada_force'])) ?></td>
                                <td class="text-end"><?= e(fmt_moeda($t['verba'])) ?></td>
                                <td class="text-end"><span class="badge text-bg-<?= e(cor_gap($t['gap'])) ?>"><?= e(fmt_moeda($t['gap'])) ?></span></td>
                                <td class="text-end"><?= e(fmt_hl($t['volume'])) ?></td>
                                <td class="text-end"><?= e($rsHl($v['aprovada_rs_hl'])) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer bg-white small text-muted">
                    R$/HL = valor Force ÷ volume vendido da revenda (<?= $v['origem_volume'] === '17.03' ? '17.03' : 'total de HL do 03.05.09' ?>) × 10, como na planilha; o total não usa o × 10.
                    <?php if ($v['volume_vendido'] === null): ?>Sem o 03.05.09 da revenda, o R$/HL fica em branco.<?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($podeImportar): ?>
    <!-- Upload dos relatórios da aba (mesma rota e validações da tela Importações) -->
    <div class="tab-pane fade" id="painelImportar" role="tabpanel">
        <div class="card" style="max-width: 40rem">
            <div class="card-body">
                <form method="post" action="<?= e(base_url('importacoes')) ?>" enctype="multipart/form-data" data-loading>
                    <?= csrf_field() ?>
                    <input type="hidden" name="voltar" value="troca">
                    <?php require dirname(__DIR__) . '/partials/campo-revenda.php'; ?>
                    <div class="mb-3">
                        <label for="tipoTroca" class="form-label">Relatório</label>
                        <select class="form-select" id="tipoTroca" name="tipo" required>
                            <option value="notas_troca">Notas de troca em rota (Promax 03.02.37)</option>
                            <option value="trocas">Solicitações de troca (Promax 03.18.05)</option>
                        </select>
                        <div class="form-text">Cada importação substitui a anterior do mesmo relatório na revenda.</div>
                    </div>
                    <div class="mb-3">
                        <label for="arquivoTroca" class="form-label">Arquivo</label>
                        <input type="file" class="form-control" id="arquivoTroca" name="arquivo" accept=".xlsx,.csv,.inf" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                        <i class="bi bi-upload"></i> Importar
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
