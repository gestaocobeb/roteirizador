<div class="row justify-content-center mt-5">
    <div class="col-sm-8 col-md-5 col-lg-4">
        <div class="card card-acesso shadow-sm">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img src="<?= e(asset('img/logo-cobeb.png')) ?>" alt="COBEB Distribuidora" class="logo-acesso mb-3">
                    <h1 class="h5 mb-1 titulo-cobeb">Roteirizador</h1>
                    <p class="text-muted small mb-0">Crítica GOD e Prioridade de entregas</p>
                </div>

                <form method="post" action="<?= e(base_url('login')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" required autofocus autocomplete="username">
                    </div>
                    <div class="mb-4">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</div>
