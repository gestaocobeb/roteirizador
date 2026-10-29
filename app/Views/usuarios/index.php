<h1 class="h3 mb-1">Usuários</h1>
<p class="text-muted">
    O <strong>perfil</strong> define o que cada um pode fazer; as <strong>abas</strong> definem quais telas aparecem para ele.
    Logística importa os dados e decide a Crítica GOD; Comercial consulta a Crítica e registra prioridades da própria Sala; Administrador vê tudo.
</p>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-person-plus"></i> Novo usuário</div>
    <div class="card-body">
        <form method="post" action="<?= e(base_url('usuarios')) ?>" data-abas-por-perfil>
            <?= csrf_field() ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="nome" class="form-label small mb-1">Nome</label>
                    <input type="text" class="form-control form-control-sm" id="nome" name="nome" maxlength="150" required>
                </div>
                <div class="col-md-3">
                    <label for="email" class="form-label small mb-1">E-mail</label>
                    <input type="email" class="form-control form-control-sm" id="email" name="email" maxlength="150" required>
                </div>
                <div class="col-6 col-md-2">
                    <label for="perfil" class="form-label small mb-1">Perfil</label>
                    <select class="form-select form-select-sm" id="perfil" name="perfil" required data-perfil>
                        <?php foreach ($perfis as $valor => $rotulo): ?>
                            <option value="<?= e($valor) ?>" <?= $valor === 'comercial' ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="revenda" class="form-label small mb-1" title="Somente Logística">Revenda</label>
                    <select class="form-select form-select-sm" id="revenda" name="revenda">
                        <option value="">Todas</option>
                        <?php foreach ($revendas as $sigla): ?>
                            <option value="<?= e($sigla) ?>"><?= e($sigla) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="senha" class="form-label small mb-1">Senha inicial</label>
                    <input type="password" class="form-control form-control-sm" id="senha" name="senha" minlength="8" required autocomplete="new-password">
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> Criar</button>
                </div>
            </div>

            <div class="mt-3">
                <span class="small text-muted me-2">Salas (Comercial):</span>
                <?php foreach ($mesas as $codigo => $revendasDaMesa): ?>
                    <input type="checkbox" class="btn-check" name="salas[]" value="<?= e((string) $codigo) ?>" id="nova_sala_<?= e((string) $codigo) ?>" autocomplete="off">
                    <label class="btn btn-sm btn-outline-primary mb-1" for="nova_sala_<?= e((string) $codigo) ?>">
                        Sala <?= e((string) $codigo) ?> <span class="small opacity-75">(<?= e($revendasDaMesa) ?>)</span>
                    </label>
                <?php endforeach; ?>
                <div class="form-text">Marque uma ou mais Salas para o usuário do Comercial. Com mais de uma, ele escolhe a Sala em uso no topo da tela.</div>
            </div>

            <div class="mt-3">
                <span class="small text-muted me-2">Abas liberadas:</span>
                <?php foreach ($abas as $chave => $aba): ?>
                    <input type="checkbox" class="btn-check" name="abas[]" value="<?= e($chave) ?>" id="nova_aba_<?= e($chave) ?>"
                           data-perfis="<?= e(implode(' ', $aba['perfis'])) ?>" checked autocomplete="off">
                    <label class="btn btn-sm btn-outline-primary mb-1" for="nova_aba_<?= e($chave) ?>">
                        <i class="bi <?= e($aba['icone']) ?>"></i> <?= e($aba['rotulo']) ?>
                    </label>
                <?php endforeach; ?>
                <div class="form-text">
                    Abas que o perfil não permite ficam desabilitadas. Administradores têm sempre todas as abas e todas as revendas.
                    Comercial: a revenda é a da Sala. Logística: escolha a revenda ou "Todas".
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Nome</th><th>Perfil</th><th>Revenda</th><th>Salas</th><th>Abas liberadas</th><th>Situação</th><th>Redefinir senha</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <?php
                    $idUsuario = (int) $u['id'];
                    $marcadas = $abasPorUsuario[$idUsuario] ?? [];
                    ?>
                    <tr class="<?= (int) $u['ativo'] === 1 ? '' : 'text-muted' ?>">
                        <td>
                            <?= e($u['nome']) ?>
                            <div class="small text-muted"><?= e($u['email']) ?></div>
                        </td>
                        <td><?= e($perfis[$u['perfil']] ?? $u['perfil']) ?></td>
                        <td>
                            <?php if ($u['perfil'] === 'logistica'): ?>
                                <form method="post" action="<?= e(base_url('usuarios/' . $idUsuario . '/revenda')) ?>" class="d-flex gap-1">
                                    <?= csrf_field() ?>
                                    <select name="revenda" class="form-select form-select-sm" style="max-width: 6.5rem" aria-label="Revenda">
                                        <option value="">Todas</option>
                                        <?php foreach ($revendas as $sigla): ?>
                                            <option value="<?= e($sigla) ?>" <?= $u['revenda'] === $sigla ? 'selected' : '' ?>><?= e($sigla) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary py-0 px-2" title="Salvar revenda"><i class="bi bi-check-lg"></i></button>
                                </form>
                            <?php elseif ($u['perfil'] === 'comercial'): ?>
                                <span class="badge badge-revenda"><?= e((string) ($u['revenda'] ?? '?')) ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-light border">Todas</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['perfil'] === 'comercial'): ?>
                                <?php $salasDoUsuario = $salasPorUsuario[$idUsuario] ?? []; ?>
                                <form method="post" action="<?= e(base_url('usuarios/' . $idUsuario . '/salas')) ?>" class="d-flex flex-wrap align-items-center gap-1" style="max-width: 16rem">
                                    <?= csrf_field() ?>
                                    <?php foreach ($mesas as $codigo => $revendasDaMesa): ?>
                                        <input type="checkbox" class="btn-check" name="salas[]" value="<?= e((string) $codigo) ?>" id="sala_<?= $idUsuario ?>_<?= e((string) $codigo) ?>"
                                               <?= in_array((int) $codigo, $salasDoUsuario, true) ? 'checked' : '' ?> autocomplete="off">
                                        <label class="btn btn-sm btn-outline-primary py-0 px-2" for="sala_<?= $idUsuario ?>_<?= e((string) $codigo) ?>" title="Revenda(s) <?= e($revendasDaMesa) ?>"><?= e((string) $codigo) ?></label>
                                    <?php endforeach; ?>
                                    <button type="submit" class="btn btn-sm btn-primary py-0 px-2" title="Salvar Salas"><i class="bi bi-check-lg"></i></button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['perfil'] === 'admin'): ?>
                                <span class="badge text-bg-light border">Todas (administrador)</span>
                            <?php else: ?>
                                <form method="post" action="<?= e(base_url('usuarios/' . $idUsuario . '/abas')) ?>" class="d-flex flex-wrap align-items-center gap-1">
                                    <?= csrf_field() ?>
                                    <?php foreach ($abas as $chave => $aba): ?>
                                        <?php if (in_array($u['perfil'], $aba['perfis'], true)): ?>
                                            <input type="checkbox" class="btn-check" name="abas[]" value="<?= e($chave) ?>" id="aba_<?= $idUsuario ?>_<?= e($chave) ?>"
                                                   <?= in_array($chave, $marcadas, true) ? 'checked' : '' ?> autocomplete="off">
                                            <label class="btn btn-sm btn-outline-primary py-0 px-2" for="aba_<?= $idUsuario ?>_<?= e($chave) ?>" title="<?= e($aba['rotulo']) ?>">
                                                <i class="bi <?= e($aba['icone']) ?>"></i> <span class="small"><?= e($aba['rotulo']) ?></span>
                                            </label>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <button type="submit" class="btn btn-sm btn-primary py-0 px-2" title="Salvar abas"><i class="bi bi-check-lg"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= (int) $u['ativo'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= (int) $u['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" action="<?= e(base_url('usuarios/' . $idUsuario . '/senha')) ?>" class="d-flex gap-1">
                                <?= csrf_field() ?>
                                <input type="password" name="senha" class="form-control form-control-sm" style="max-width: 9rem" minlength="8" placeholder="Nova senha" required autocomplete="new-password" aria-label="Nova senha">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Redefinir"><i class="bi bi-key"></i></button>
                            </form>
                        </td>
                        <td class="text-end">
                            <form method="post" action="<?= e(base_url('usuarios/' . $idUsuario . '/status')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm <?= (int) $u['ativo'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                    <?= (int) $u['ativo'] === 1 ? 'Desativar' : 'Ativar' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
