<?php use App\Core\Csrf; ?>
<div class="auth-card">
  <div class="card">
    <div class="card-body p-4 p-md-5">
      <div class="mb-4">
        <h1 class="h4 mb-2">Entrar</h1>
        <p class="text-muted-app mb-0">Acesse seu painel e seus agendamentos.</p>
      </div>
      <form method="post" action="<?= e(url('/login')) ?>">
        <?= Csrf::field() ?>
        <div class="mb-3">
          <label class="form-label">E-mail</label>
          <input class="form-control" type="email" name="email" autocomplete="email" required autofocus>
        </div>
        <div class="mb-4">
          <label class="form-label">Senha</label>
          <input class="form-control" type="password" name="password" autocomplete="current-password" required>
        </div>
        <button class="btn btn-dark w-100">Entrar</button>
      </form>
      <div class="small text-center text-muted-app mt-4">Ainda não tem conta? <a href="<?= e(url('/cadastro')) ?>">Criar conta</a></div>
    </div>
  </div>
</div>
