<?php

use App\Core\Csrf;
?>
<section class="page-header">
  <div>
    <div class="small text-muted-app mb-1">Conta</div>
    <h1 class="h3 mb-1">Alterar senha</h1>
    <p class="text-muted-app mb-0">Confirme sua senha atual antes de definir uma nova.</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= e(url('/painel/perfil')) ?>"><i class="bi bi-arrow-left me-2"></i>Voltar ao perfil</a>
</section>

<div class="row justify-content-center">
  <div class="col-lg-7 col-xl-6">
    <div class="card">
      <div class="card-body p-4 p-xl-5">
        <form method="post" action="<?= e(url('/painel/perfil/senha')) ?>">
          <?= Csrf::field() ?>
          <div class="mb-3">
            <label class="form-label">Senha atual</label>
            <input class="form-control" type="password" name="current_password" autocomplete="current-password" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Nova senha</label>
            <input class="form-control" type="password" name="new_password" minlength="8" maxlength="255" autocomplete="new-password" required>
            <div class="form-text">Use pelo menos 8 caracteres.</div>
          </div>
          <div class="mb-4">
            <label class="form-label">Confirmar nova senha</label>
            <input class="form-control" type="password" name="new_password_confirmation" minlength="8" maxlength="255" autocomplete="new-password" required>
          </div>
          <button class="btn btn-dark w-100" type="submit"><i class="bi bi-shield-lock me-2"></i>Atualizar senha</button>
        </form>
      </div>
    </div>
  </div>
</div>
