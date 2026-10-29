<div class="row justify-content-center mt-5">
    <div class="col-sm-10 col-md-6 col-lg-5">
        <div class="card card-acesso shadow-sm">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <img src="<?= e(asset('img/logo-cobeb.png')) ?>" alt="COBEB Distribuidora" class="logo-acesso">
                </div>
                <h1 class="h5 mb-1 titulo-cobeb"><i class="bi bi-shield-lock"></i> Primeiro acesso</h1>
                <p class="text-muted small mb-4">
                    Nenhum usuário cadastrado. Crie o administrador do Roteirizador.
                    Esta tela deixa de existir assim que o primeiro usuário é criado.
                </p>

                <form method="post" action="<?= e(base_url('primeiro-acesso')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome" maxlength="150" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="150" required>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label for="senha" class="form-label">Senha</label>
                            <input type="password" class="form-control" id="senha" name="senha" minlength="8" required autocomplete="new-password">
                        </div>
                        <div class="col-sm-6">
                            <label for="senha_confirmacao" class="form-label">Confirmar senha</label>
                            <input type="password" class="form-control" id="senha_confirmacao" name="senha_confirmacao" minlength="8" required autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Criar administrador</button>
                </form>
            </div>
        </div>
    </div>
</div>
