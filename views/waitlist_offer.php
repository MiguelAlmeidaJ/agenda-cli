<?php

use App\Core\Csrf;
?>
<section class="page-header">
  <div>
    <div class="small text-muted-app mb-1">Lista de espera</div>
    <h1 class="h3 mb-1">Uma vaga ficou disponível</h1>
    <p class="text-muted-app mb-0">Revise os dados antes de confirmar. A disponibilidade será validada novamente ao enviar.</p>
  </div>
</section>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card"><div class="card-body p-4 p-lg-5">
      <div class="text-center mb-4">
        <div class="icon-box mx-auto mb-3"><i class="bi bi-calendar2-check"></i></div>
        <h2 class="h4 mb-1"><?= e($offer['service_name']) ?></h2>
        <div class="text-muted-app"><?= e($offer['establishment_name']) ?></div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-sm-4"><div class="border rounded-3 p-3 h-100"><span class="small text-muted-app d-block">Data</span><strong><?= e(date('d/m/Y', strtotime((string) $offer['slot_start']))) ?></strong></div></div>
        <div class="col-sm-4"><div class="border rounded-3 p-3 h-100"><span class="small text-muted-app d-block">Horário</span><strong><?= e(date('H:i', strtotime((string) $offer['slot_start']))) ?></strong></div></div>
        <div class="col-sm-4"><div class="border rounded-3 p-3 h-100"><span class="small text-muted-app d-block">Profissional</span><strong><?= e($offer['employee_name']) ?></strong></div></div>
      </div>

      <?php if (!empty($offer['offer_expires_at'])): ?>
        <div class="alert alert-warning small">
          Esta oferta expira em <?= e(date('d/m/Y H:i', strtotime((string) $offer['offer_expires_at']))) ?>. O horário não fica bloqueado; ele será revalidado no momento da confirmação.
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('/lista-espera/oferta/' . $token)) ?>">
        <?= Csrf::field() ?>
        <button class="btn btn-success btn-lg w-100" type="submit"><i class="bi bi-check-circle me-2"></i>Confirmar esta vaga</button>
      </form>
      <a class="btn btn-link w-100 mt-2" href="<?= e(url('/painel/minha-lista-espera')) ?>">Ver minha lista de espera</a>
    </div></div>
  </div>
</div>
