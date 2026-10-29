<h1 class="h3 mb-1">Importações</h1>
<p class="text-muted">Envie os exports do Cora e do Promax em <strong>.xlsx</strong> ou <strong>.csv</strong>. As colunas são localizadas pelo nome do cabeçalho.</p>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <form method="post" action="<?= e(base_url('importacoes')) ?>" enctype="multipart/form-data" data-loading>
                    <?= csrf_field() ?>

                    <?php require dirname(__DIR__) . '/partials/campo-revenda.php'; ?>

                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo de arquivo</label>
                        <select class="form-select" id="tipo" name="tipo" required data-toggle-cora>
                            <option value="">Selecione...</option>
                            <?php foreach ($tipos as $valor => $descricao): ?>
                                <option value="<?= e($valor) ?>"><?= e($descricao) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="campoDataCora">
                        <label for="data" class="form-label">Data de entrega dos pedidos</label>
                        <input type="date" class="form-control" id="data" name="data" value="<?= e($dataPadrao) ?>">
                        <div class="form-text">Reimportar a mesma data substitui os pedidos anteriores dela.</div>
                    </div>

                    <div class="mb-3">
                        <label for="arquivo" class="form-label">Arquivo</label>
                        <input type="file" class="form-control" id="arquivo" name="arquivo" accept=".xlsx,.csv,.inf" required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                        <i class="bi bi-upload"></i> Importar
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body small">
                <h2 class="h6">O que cada importação faz</h2>
                <ul class="mb-0 ps-3">
                    <li><strong>Pedidos Cora</strong>: base do dia. Use o export itemizado (com "Volume hectolitro") para o cálculo de HL. Pedidos com Tipo pedido <strong>REPOSICAO</strong> são ignorados.</li>
                    <li><strong>01.20.11</strong>: nome, cidade, setor e dia de visita dos clientes.</li>
                    <li><strong>03.02.37</strong>: indicador "Já comprou?" (quantidade de notas).</li>
                    <li><strong>03.02.24</strong>: indicador de Black List.</li>
                    <li><strong>Top Line</strong> e <strong>Travas</strong>: listas de apoio (colunas COD, NOME...).</li>
                    <li><strong>03.18.05</strong>: solicitações de troca consultadas na aba Troca (aceita o arquivo <code>.csv.inf</code> gerado pelo Promax).</li>
                    <li><strong>Notas de troca (03.02.37)</strong>: notas em rota do dia, listadas por setor na aba Troca. Não confundir com o 03.02.37 do histórico de notas.</li>
                    <li><strong>03.05.09</strong>: volume vendido por setor, base da Verba de troca (aba Aprovação troca). O <strong>17.03</strong> é opcional: se importado, o volume vendido do R$/HL passa a ser o dele (saída − devolução).</li>
                </ul>
                <p class="mb-0 mt-2 text-muted">Também é possível enviar as planilhas antigas: o sistema procura a aba com o nome correspondente (Cora, 01 20 11, TOP LINE, TRAVAS...).</p>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Importações recentes</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Quando</th><th>Revenda</th><th>Tipo</th><th>Arquivo</th><th class="text-end">Registros</th><th>Por</th><th>Situação</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentes): ?>
                            <tr><td colspan="8" class="text-muted text-center py-3">Nenhuma importação realizada.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($recentes as $item): ?>
                            <?php
                            $descricao = $tipos[$item['tipo']] ?? $item['tipo'];
                            $confirmacao = $item['tipo'] === 'cora'
                                ? "Excluir a importação do Cora da revenda {$item['revenda']} de entrega em " . fmt_data($item['data_referencia']) . "?\n\nOs pedidos desta data da revenda {$item['revenda']} serão apagados. Prioridades e decisões da data são mantidas e voltam a valer se o Cora for reimportado."
                                : "Excluir a importação \"{$descricao}\" da revenda {$item['revenda']}?\n\nEsta base da revenda {$item['revenda']} ficará vazia até uma nova importação.";
                            ?>
                            <tr class="<?= $item['situacao'] === 'vigente' ? '' : 'text-muted' ?>">
                                <td class="text-nowrap"><?= e(fmt_data_hora($item['criado_em'])) ?></td>
                                <td><span class="badge badge-revenda"><?= e($item['revenda']) ?></span></td>
                                <td>
                                    <?= e($descricao) ?>
                                    <?php if ($item['data_referencia']): ?>
                                        <span class="badge text-bg-light">entrega <?= e(fmt_data($item['data_referencia'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= e($item['arquivo_nome']) ?></td>
                                <td class="text-end"><?= e(number_format((int) $item['total_linhas'], 0, ',', '.')) ?></td>
                                <td><?= e($item['usuario_nome']) ?></td>
                                <td class="text-nowrap">
                                    <?php if ($item['situacao'] === 'vigente'): ?>
                                        <span class="badge text-bg-success">Vigente</span>
                                    <?php elseif ($item['situacao'] === 'excluida'): ?>
                                        <span class="badge text-bg-danger" title="<?= e('Por ' . $item['excluida_por_nome'] . ' em ' . fmt_data_hora($item['excluida_em'])) ?>" data-bs-toggle="tooltip">Excluída</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary" title="Dados substituídos por uma importação mais recente" data-bs-toggle="tooltip">Substituída</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($item['situacao'] === 'vigente'): ?>
                                        <form method="post" action="<?= e(base_url('importacoes/' . (int) $item['id'] . '/excluir')) ?>" data-confirmar="<?= e($confirmacao) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir importação"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
