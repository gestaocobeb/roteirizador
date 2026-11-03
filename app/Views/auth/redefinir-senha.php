<div class="row justify-content-center mt-5">
    <div class="col-sm-10 col-md-6 col-lg-5">
        <div class="card card-acesso shadow-sm">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <img src="<?= e(asset('img/logo-cobeb.png')) ?>" alt="COBEB Distribuidora" class="logo-acesso">
                </div>
                <h1 class="h5 mb-1 titulo-cobeb"><i class="bi bi-shield-lock"></i> Nova senha</h1>
                <p class="text-muted small mb-4">Defina a nova senha de <strong><?= e($email) ?></strong>.</p>

                <form method="post" action="<?= e(base_url('redefinir-senha')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <input type="email" class="d-none" name="email" value="<?= e($email) ?>" autocomplete="username" tabindex="-1" aria-hidden="true">
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label for="senha" class="form-label">Nova senha</label>
                            <input type="password" class="form-control" id="senha" name="senha" minlength="8" required autofocus autocomplete="new-password">
                        </div>
                        <div class="col-sm-6">
                            <label for="senha_confirmacao" class="form-label">Confirmar senha</label>
                            <input type="password" class="form-control" id="senha_confirmacao" name="senha_confirmacao" minlength="8" required autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Salvar nova senha</button>
                </form>
            </div>
        </div>
    </div>
</div>
