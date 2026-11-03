<div class="row justify-content-center mt-5">
    <div class="col-sm-8 col-md-5 col-lg-4">
        <div class="card card-acesso shadow-sm">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <img src="<?= e(asset('img/logo-cobeb.png')) ?>" alt="COBEB Distribuidora" class="logo-acesso">
                </div>
                <h1 class="h5 mb-1 titulo-cobeb"><i class="bi bi-key"></i> Esqueci minha senha</h1>
                <p class="text-muted small mb-4">
                    Informe o e-mail do seu usuário. Enviaremos um link para você criar uma nova senha.
                </p>

                <form method="post" action="<?= e(base_url('esqueci-senha')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-4">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="150" required autofocus autocomplete="username">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Enviar link</button>
                </form>
                <div class="text-center mt-3">
                    <a href="<?= e(base_url('login')) ?>" class="small"><i class="bi bi-arrow-left"></i> Voltar para o login</a>
                </div>
            </div>
        </div>
    </div>
</div>
