<?php

use App\Core\Csrf;

$startsAt = strtotime((string) $appointment['starts_at']);
?>
<section class="page-header">
  <div class="mx-auto" style="max-width: 760px">
    <div class="small text-muted-app mb-2">Confirmação de presença</div>
    <h1 class="h3 mb-2"><?= e($appointment['establishment_name']) ?></h1>
    <p class="text-muted-app mb-0">Revise seu atendimento abaixo.</p>
  </div>
</section>

<div class="mx-auto" style="max-width: 760px">
  <div class="card">
    <div class="card-body p-4 p-lg-5">
      <div class="row g-3 mb-4">
        <div class="col-sm-4">
          <div class="border rounded-3 p-3 h-100">
            <span class="small text-muted-app d-block">Data</span>
            <strong><?= e(date('d/m/Y', $startsAt)) ?></strong>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="border rounded-3 p-3 h-100">
            <span class="small text-muted-app d-block">Horário</span>
            <strong><?= e(date('H:i', $startsAt)) ?></strong>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="border rounded-3 p-3 h-100">
            <span class="small text-muted-app d-block">Profissional</span>
            <strong><?= e($appointment['employee_name']) ?></strong>
          </div>
        </div>
      </div>

      <div class="mb-4">
        <span class="small text-muted-app d-block">Serviço</span>
        <strong><?= e($appointment['service_name']) ?></strong>
      </div>

      <?php if ($state === 'confirmed'): ?>
        <div class="alert alert-success mb-0">
          <div class="fw-semibold"><i class="bi bi-check-circle me-2"></i>Presença confirmada</div>
          <div class="small mt-1">Sua confirmação já foi registrada para este atendimento.</div>
        </div>
      <?php elseif ($state === 'available'): ?>
        <div class="alert alert-light border">
          Ao confirmar, o estabelecimento verá que você pretende comparecer. O status do agendamento continua separado da confirmação de presença.
        </div>
        <form method="post" action="<?= e(url('/agendamentos/presenca/' . $token . '/confirmar')) ?>">
          <?= Csrf::field() ?>
          <button class="btn btn-dark btn-lg w-100" type="submit">
            <i class="bi bi-check2-circle me-2"></i>Confirmar minha presença
          </button>
        </form>
      <?php else: ?>
        <div class="alert alert-warning mb-0">
          Este link expirou ou o agendamento não está mais disponível para confirmação de presença.
        </div>
      <?php endif; ?>

      <div class="text-center mt-4">
        <a class="small text-decoration-none" href="<?= e(url('/estabelecimentos/' . $appointment['establishment_slug'])) ?>">Ver estabelecimento</a>
      </div>
    </div>
  </div>
</div>
