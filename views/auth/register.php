<?php use App\Core\Csrf; ?>
<div class="auth-card">
  <div class="card">
    <div class="card-body p-4 p-md-5">
      <div class="mb-4">
        <h1 class="h4 mb-2">Criar conta</h1>
        <p class="text-muted-app mb-0">Cadastre-se como cliente para reservar horários.</p>
      </div>
      <form method="post" action="<?= e(url('/cadastro')) ?>">
        <?= Csrf::field() ?>
        <div class="mb-3">
          <label class="form-label">Nome</label>
          <input class="form-control" name="name" minlength="3" maxlength="120" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">E-mail</label>
          <input class="form-control" type="email" name="email" autocomplete="email" required>
        </div>
        <div class="mb-4">
          <label class="form-label">Senha</label>
          <input class="form-control" type="password" name="password" minlength="8" autocomplete="new-password" required>
          <div class="form-text">Use pelo menos 8 caracteres.</div>
        </div>
        <button class="btn btn-dark w-100">Criar conta</button>
      </form>
      <div class="small text-center text-muted-app mt-4">Já tem conta? <a href="<?= e(url('/login')) ?>">Entrar</a></div>
    </div>
  </div>
</div>
