<?php
$nomesDias = ['Mon' => 'segunda', 'Tue' => 'terça', 'Wed' => 'quarta', 'Thu' => 'quinta', 'Fri' => 'sexta'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">Bees Loat</h1>
        <p class="text-muted mb-0">Gera o CSV de dias de entrega do BEES a partir do Relatório Bees e da rotina 0105070402</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <form method="post" action="<?= e(base_url('bees-loat/gerar')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="pasta" class="form-label">Relatório Bees</label>
                        <input type="file" class="form-control" id="pasta" name="pasta" accept=".csv" required>
                    </div>

                    <div class="mb-3">
                        <label for="vde" class="form-label">Rotina 0105070402</label>
                        <input type="file" class="form-control" id="vde" name="vde[]" accept=".inf,.csv,.txt" multiple required>
                        <div class="form-text">Selecione um ou mais arquivos (segure Ctrl para marcar vários).</div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-download"></i> Gerar e baixar CSV
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">O que é feito no arquivo</div>
            <div class="card-body">
                <ol class="mb-3">
                    <li>Exclui a coluna D (Settings_Type).</li>
                    <li>Apaga o conteúdo da coluna Reference_Date, mantendo só o título.</li>
                    <li>Nos dias da semana (Mon a Sun), troca todo FREE por NO.</li>
                    <li>Marca FREE no dia de entrega de cada cliente, conforme o dia de visita do VDE e do VDI (colunas AY e BH da rotina 0105070402).</li>
                </ol>
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>Dia de visita</th><th>Entrega (FREE)</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entregas as $visita => $dia): ?>
                            <tr><td><?= e($visita) ?></td><td><?= e($nomesDias[$dia] ?? $dia) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="form-text">Sábado e domingo de visita não geram entrega. O cliente é localizado pelo Cód PDV (rotina 0105070402) = ERP_POC_ID (Relatório Bees).</div>
            </div>
        </div>
    </div>
</div>
