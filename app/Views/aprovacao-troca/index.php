<?php
$rsHl = fn (?float $valor): string => $valor === null ? '—' : number_format($valor, 4, ',', '.');
// GAP em selo colorido: vermelho (negativo), amarelo (até R$ 10,00), verde (acima).
$gap = fn (float $valor): string => '<span class="badge text-bg-' . e(cor_gap($valor)) . '">' . e(fmt_moeda($valor)) . '</span>';
$t = $matriz['totais'] ?? [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">Aprovação troca</h1>
        <p class="text-muted mb-0"><?= $revenda !== '' ? 'Revenda ' . e($revenda) : 'Nenhuma revenda liberada para o usuário' ?></p>
    </div>
    <?php if (count($revendas) > 1): ?>
        <form method="get" action="<?= e(base_url('aprovacao-troca')) ?>">
            <label for="filtroRevendaVerba" class="form-label small mb-1">Revenda</label>
            <select class="form-select form-select-sm" id="filtroRevendaVerba" name="revenda" data-autosubmit>
                <?php foreach ($revendas as $sigla): ?>
                    <option value="<?= e($sigla) ?>" <?= $sigla === $revenda ? 'selected' : '' ?>><?= e($sigla) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Filtrar</button></noscript>
        </form>
    <?php endif; ?>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $filtros['ativo'] ? '' : 'active' ?>" data-bs-toggle="tab" data-bs-target="#painelVerba" type="button" role="tab">
            <i class="bi bi-cash-coin"></i> Verba de troca
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $filtros['ativo'] ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#painelSolicitacoesVerba" type="button" role="tab">
            <i class="bi bi-list-check"></i> Solicitações 03.18.05
        </button>
    </li>
    <?php if ($podeImportar): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#painelImportarVerba" type="button" role="tab">
                <i class="bi bi-upload"></i> Importar relatórios
            </button>
        </li>
    <?php endif; ?>
</ul>

<div class="tab-content">
    <!-- Verba de troca (aba MATRIZ da planilha "Reposição Novo") -->
    <div class="tab-pane fade <?= $filtros['ativo'] ? '' : 'show active' ?>" id="painelVerba" role="tabpanel">
        <p class="small text-muted mb-2">
            <?php foreach ($relatorios as $tipo => $rotulo): ?>
                <span class="me-3">
                    <strong><?= e($rotulo) ?></strong>:
                    <?php if ($importacoes[$tipo]): ?>
                        <?= e(fmt_data_hora($importacoes[$tipo]['criado_em'])) ?>
                    <?php else: ?>
                        <span class="text-danger">não importado</span>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </p>

        <?php if ($matriz === null): ?>
            <div class="alert alert-warning">Sem revenda para exibir a verba de troca.</div>
        <?php else: ?>
            <?php if (in_array(null, $importacoes, true)): ?>
                <div class="alert alert-warning small py-2">
                    Importe os dois relatórios da revenda <?= e($revenda) ?> (03.18.05 e 03.05.09) para a verba ficar completa.
                    Sem o 03.05.09, a verba dos setores fica zerada e não há R$/HL.
                </div>
            <?php endif; ?>

            <div class="row g-2 mb-3">
                <div class="col-6 col-lg">
                    <div class="card h-100"><div class="card-body py-2">
                        <div class="small text-muted">Volume vendido (HL)</div>
                        <div class="fs-5 fw-semibold"><?= $matriz['volume_vendido'] === null ? '—' : e(fmt_hl($matriz['volume_vendido'])) ?></div>
                        <div class="small text-muted">
                            <?= $matriz['origem_volume'] === '17.03' ? '17.03: saída − devolução' : 'Total de HL do 03.05.09' ?>
                        </div>
                    </div></div>
                </div>
                <div class="col-6 col-lg">
                    <div class="card h-100"><div class="card-body py-2">
                        <div class="small text-muted">Verba total</div>
                        <div class="fs-5 fw-semibold"><?= e(fmt_moeda($t['verba'])) ?></div>
                        <div class="small text-muted">10% do HL dos setores</div>
                    </div></div>
                </div>
                <div class="col-6 col-lg">
                    <div class="card h-100"><div class="card-body py-2">
                        <div class="small text-muted">Force aprovado (faturado)</div>
                        <div class="fs-5 fw-semibold"><?= e(fmt_moeda($t['aprovada_force'])) ?></div>
                        <div class="small text-muted">R$/HL <?= e($rsHl($matriz['aprovada_rs_hl'])) ?></div>
                    </div></div>
                </div>
                <div class="col-6 col-lg">
                    <div class="card h-100"><div class="card-body py-2">
                        <div class="small text-muted">GAP (verba − Force aprovado)</div>
                        <div class="fs-5"><?= $gap($t['gap']) ?></div>
                        <div class="small text-muted">Saldo para aprovar</div>
                    </div></div>
                </div>
                <div class="col-12 col-lg">
                    <div class="card h-100"><div class="card-body py-2">
                        <div class="small text-muted">Force pendente</div>
                        <div class="fs-5 fw-semibold"><?= e(fmt_moeda($t['pendente_force'])) ?></div>
                        <div class="small text-muted">R$/HL <?= e($rsHl($matriz['pendente_rs_hl'])) ?></div>
                    </div></div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-grid-3x3"></i> Verba por setor</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th rowspan="2" class="align-middle">Setor (RN)</th>
                                <th colspan="5" class="text-center border-start">Pendente</th>
                                <th colspan="6" class="text-center border-start">Faturado (aprovado)</th>
                            </tr>
                            <tr>
                                <th class="text-end border-start">Promax</th>
                                <th class="text-end">Force</th>
                                <th class="text-end">Customer</th>
                                <th class="text-end">R$/HL</th>
                                <th class="text-center" title="SIM quando o GAP do setor cobre o Force pendente">Cabe na verba?</th>
                                <th class="text-end border-start">Promax</th>
                                <th class="text-end">Force</th>
                                <th class="text-end" title="Volume HL x 10%">Verba</th>
                                <th class="text-end" title="Verba - Force aprovado">GAP</th>
                                <th class="text-end" title="Quantidade Hecto do 03.05.09">Volume (HL)</th>
                                <th class="text-end">R$/HL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matriz['setores'] as $s): ?>
                                <tr>
                                    <td class="fw-semibold">
                                        <a href="<?= e(base_url('aprovacao-troca?solicitacoes=1&setor=' . $s['setor'])) ?>" title="Ver as solicitações Force pendentes do setor"><?= e((string) $s['setor']) ?></a>
                                    </td>
                                    <td class="text-end border-start"><?= e(fmt_moeda($s['pendente_promax'])) ?></td>
                                    <td class="text-end"><?= e(fmt_moeda($s['pendente_force'])) ?></td>
                                    <td class="text-end"><?= e(fmt_moeda($s['pendente_customer'])) ?></td>
                                    <td class="text-end"><?= e($rsHl($s['pendente_rs_hl'])) ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $s['cabe_na_verba'] ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $s['cabe_na_verba'] ? 'SIM' : 'NÃO' ?></span>
                                    </td>
                                    <td class="text-end border-start"><?= e(fmt_moeda($s['aprovada_promax'])) ?></td>
                                    <td class="text-end"><?= e(fmt_moeda($s['aprovada_force'])) ?></td>
                                    <td class="text-end"><?= e(fmt_moeda($s['verba'])) ?></td>
                                    <td class="text-end"><?= $gap($s['gap']) ?></td>
                                    <td class="text-end"><?= e(fmt_hl($s['volume'])) ?></td>
                                    <td class="text-end"><?= e($rsHl($s['aprovada_rs_hl'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td>Total</td>
                                <td class="text-end border-start"><?= e(fmt_moeda($t['pendente_promax'])) ?></td>
                                <td class="text-end"><?= e(fmt_moeda($t['pendente_force'])) ?></td>
                                <td class="text-end"><?= e(fmt_moeda($t['pendente_customer'])) ?></td>
                                <td class="text-end"><?= e($rsHl($matriz['pendente_rs_hl'])) ?></td>
                                <td></td>
                                <td class="text-end border-start"><?= e(fmt_moeda($t['aprovada_promax'])) ?></td>
                                <td class="text-end"><?= e(fmt_moeda($t['aprovada_force'])) ?></td>
                                <td class="text-end"><?= e(fmt_moeda($t['verba'])) ?></td>
                                <td class="text-end"><?= $gap($t['gap']) ?></td>
                                <td class="text-end"><?= e(fmt_hl($t['volume'])) ?></td>
                                <td class="text-end"><?= e($rsHl($matriz['aprovada_rs_hl'])) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer bg-white small text-muted">
                    R$/HL = valor Force ÷ volume vendido da revenda × 10, como na planilha. Os totais de R$/HL não usam o × 10.
                    <?php if ($foraDoCadastro): ?>
                        <br>Setores dos relatórios fora do cadastro (não entram na verba): <?= e(implode(', ', $foraDoCadastro)) ?>.
                        Para incluí-los, cadastre-os em Setores.
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3">
                <?php foreach (['Motoristas' => [$motoristas, 'bi-truck'], 'Ajudantes de entrega' => [$ajudantes, 'bi-person']] as $titulo => [$lista, $icone]): ?>
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header bg-white fw-semibold">
                                <i class="bi <?= e($icone) ?>"></i> <?= e($titulo) ?>
                                <span class="small text-muted fw-normal">&middot; trocas Promax aprovadas</span>
                            </div>
                            <?php if (!$lista): ?>
                                <div class="card-body text-muted small">Nenhuma troca Promax aprovada com <?= $titulo === 'Motoristas' ? 'motorista' : 'ajudante' ?> informado.</div>
                            <?php else: ?>
                                <div class="table-responsive" style="max-height: 28rem">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light sticky-top"><tr><th>Nome</th><th class="text-end">Promax</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($lista as $nome => $valor): ?>
                                                <tr><td><?= e((string) $nome) ?></td><td class="text-end"><?= e(fmt_moeda($valor)) ?></td></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="table-light fw-semibold"><tr><td>Total</td><td class="text-end"><?= e(fmt_moeda(array_sum($lista))) ?></td></tr></tfoot>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Solicitações do 03.18.05 com a situação na verba do setor -->
    <div class="tab-pane fade <?= $filtros['ativo'] ? 'show active' : '' ?>" id="painelSolicitacoesVerba" role="tabpanel">
        <form method="get" action="<?= e(base_url('aprovacao-troca')) ?>" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="solicitacoes" value="1">
            <div class="col-6 col-md-2">
                <label for="filtroStatus" class="form-label small mb-1">Status</label>
                <select class="form-select form-select-sm" id="filtroStatus" name="status" data-autosubmit>
                    <?php foreach (['pendente' => 'Pendente', 'aprovada' => 'Aprovada', 'reprovada' => 'Reprovada', '' => 'Todos'] as $valor => $rotulo): ?>
                        <option value="<?= e($valor) ?>" <?= $filtros['status'] === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="filtroSistema" class="form-label small mb-1">Sistema Origem</label>
                <select class="form-select form-select-sm" id="filtroSistema" name="sistema" data-autosubmit>
                    <?php foreach (['force' => 'Force', 'promax' => 'Promax', 'customer' => 'Customer', '' => 'Todos'] as $valor => $rotulo): ?>
                        <option value="<?= e($valor) ?>" <?= $filtros['sistema'] === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="filtroVerba" class="form-label small mb-1">Na verba</label>
                <select class="form-select form-select-sm" id="filtroVerba" name="verba" data-autosubmit>
                    <?php foreach (['' => 'Todas', 'nao_cabe' => 'Não cabe', 'cabe' => 'Cabe'] as $valor => $rotulo): ?>
                        <option value="<?= e($valor) ?>" <?= $filtros['verba'] === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="filtroSetorVerba" class="form-label small mb-1">Setor</label>
                <select class="form-select form-select-sm" id="filtroSetorVerba" name="setor" data-autosubmit>
                    <option value="">Todos</option>
                    <?php foreach ($matriz['setores'] ?? [] as $s): ?>
                        <option value="<?= e((string) $s['setor']) ?>" <?= $filtros['setor'] === $s['setor'] ? 'selected' : '' ?>>
                            Setor <?= e((string) $s['setor']) ?> (GAP <?= e(fmt_moeda($s['gap'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="filtroClienteVerba" class="form-label small mb-1">Código Cliente</label>
                <input type="number" class="form-control form-control-sm" id="filtroClienteVerba" name="cliente" min="1" value="<?= e((string) ($filtros['cliente'] ?? '')) ?>">
            </div>
            <div class="col-6 col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="<?= e(base_url('aprovacao-troca?solicitacoes=1')) ?>" class="btn btn-sm btn-outline-secondary">Limpar</a>
            </div>
        </form>

        <?php
        $valorTotal = array_sum(array_column($solicitacoes, 'valor'));
        $valorNaoCabe = array_sum(array_column(array_filter($solicitacoes, fn ($s) => $s['verba'] === 'nao_cabe'), 'valor'));
        ?>
        <p class="small mb-2">
            <strong><?= e((string) count($solicitacoes)) ?></strong> solicitação(ões) &middot; <?= e(fmt_moeda($valorTotal)) ?>
            <?php if ($valorNaoCabe > 0): ?>
                &middot; <span class="text-danger fw-semibold">não cabe na verba: <?= e(fmt_moeda($valorNaoCabe)) ?></span>
            <?php endif; ?>
        </p>
        <p class="small text-muted">
            "Na verba" vale para as pendências <strong>Force</strong>, que consomem a verba do setor. Elas são analisadas da mais antiga para a mais nova:
            cabe enquanto o valor couber no saldo (GAP do setor menos as pendências anteriores que couberam). A que não cabe fica de fora, e as seguintes ainda podem caber.
            Promax e Customer não usam a verba.
        </p>

        <?php if (!$solicitacoes): ?>
            <div class="alert alert-info">Nenhuma solicitação no filtro.</div>
        <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Setor</th><th>Solicitação</th><th>Data</th><th>Cliente</th><th>Status</th><th>Sistema</th>
                                <th>Produtos</th><th class="text-end">Valor</th><th class="text-center">Na verba</th><th class="text-end" title="Saldo da verba do setor depois desta solicitação">Saldo</th>
                                <th>Motorista</th><th>Observação</th>
                                <th style="min-width: 17rem" title="Aparece para o Comercial em Troca > Solicitações por cliente">Justificativa para o Comercial</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitacoes as $s): ?>
                                <?php $corStatus = ['aprovada' => 'success', 'reprovada' => 'danger', 'pendente' => 'warning'][mb_strtolower((string) $s['status_solicitacao'])] ?? 'secondary'; ?>
                                <tr class="<?= $s['verba'] === 'nao_cabe' ? 'table-danger' : '' ?>">
                                    <td><?= e((string) ($s['setor_codigo'] ?? '—')) ?></td>
                                    <td><?= e($s['solicitacao']) ?></td>
                                    <td class="text-nowrap"><?= e(fmt_data($s['data_solicitacao'])) ?></td>
                                    <td><?= e((string) $s['cliente_codigo']) ?> - <?= e($s['cliente_nome']) ?></td>
                                    <td><span class="badge text-bg-<?= e($corStatus) ?>"><?= e($s['status_solicitacao']) ?></span></td>
                                    <td><?= e($s['sistema_origem']) ?></td>
                                    <td class="small"><?= e($s['produtos']) ?></td>
                                    <td class="text-end text-nowrap"><?= e(fmt_moeda($s['valor'])) ?></td>
                                    <td class="text-center">
                                        <?php if ($s['verba'] === 'cabe'): ?>
                                            <span class="badge text-bg-success">Cabe</span>
                                        <?php elseif ($s['verba'] === 'nao_cabe'): ?>
                                            <span class="badge text-bg-danger">Não cabe</span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap"><?= $s['saldo_apos'] === null ? '' : e(fmt_moeda($s['saldo_apos'])) ?></td>
                                    <td class="small"><?= e($s['motorista_nome']) ?></td>
                                    <td class="small"><?= e($s['observacao']) ?></td>
                                    <td>
                                        <form method="post" action="<?= e(base_url('aprovacao-troca/justificativa')) ?>" class="d-flex gap-1 align-items-start">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="revenda" value="<?= e($revenda) ?>">
                                            <input type="hidden" name="solicitacao" value="<?= e($s['solicitacao']) ?>">
                                            <input type="hidden" name="cliente" value="<?= e((string) $s['cliente_codigo']) ?>">
                                            <input type="hidden" name="f_status" value="<?= e($filtros['status']) ?>">
                                            <input type="hidden" name="f_sistema" value="<?= e($filtros['sistema']) ?>">
                                            <input type="hidden" name="f_verba" value="<?= e($filtros['verba']) ?>">
                                            <input type="hidden" name="f_setor" value="<?= e((string) ($filtros['setor'] ?? '')) ?>">
                                            <input type="hidden" name="f_cliente" value="<?= e((string) ($filtros['cliente'] ?? '')) ?>">
                                            <textarea name="justificativa" rows="1" maxlength="500" class="form-control form-control-sm"
                                                      placeholder="Ex.: reprovada, produto fora da validade de troca" aria-label="Justificativa da solicitação <?= e($s['solicitacao']) ?>"><?= e((string) $s['justificativa']) ?></textarea>
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Salvar justificativa (vazio = remover)"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        <?php if ($s['justificativa']): ?>
                                            <div class="small text-muted mt-1"><?= e($s['justificativa_por']) ?> &middot; <?= e(fmt_data_hora($s['justificativa_em'])) ?></div>
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

    <?php if ($podeImportar): ?>
    <!-- Upload dos relatórios da verba (mesma rota e validações da tela Importações) -->
    <div class="tab-pane fade" id="painelImportarVerba" role="tabpanel">
        <div class="card" style="max-width: 40rem">
            <div class="card-body">
                <form method="post" action="<?= e(base_url('importacoes')) ?>" enctype="multipart/form-data" data-loading>
                    <?= csrf_field() ?>
                    <input type="hidden" name="voltar" value="aprovacao-troca">
                    <?php require dirname(__DIR__) . '/partials/campo-revenda.php'; ?>
                    <div class="mb-3">
                        <label for="tipoVerba" class="form-label">Relatório</label>
                        <select class="form-select" id="tipoVerba" name="tipo" required>
                            <option value="trocas">Solicitações de troca (Promax 03.18.05) - todos os status</option>
                            <option value="vendas_setor">Volume vendido por setor (Promax 03.05.09)</option>
                        </select>
                        <div class="form-text">
                            Cada importação substitui a anterior do mesmo relatório na revenda. O 03.18.05 é o mesmo da aba Troca
                            (aprovadas, pendentes e reprovadas no mesmo arquivo).
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="arquivoVerba" class="form-label">Arquivo</label>
                        <input type="file" class="form-control" id="arquivoVerba" name="arquivo" accept=".xlsx,.csv,.inf" required>
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
