<?php

use App\Core\Csrf;

$days = [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'];
?>
<section class="page-header">
  <div>
    <div class="small text-muted-app mb-1">Configuração</div>
    <h1 class="h3 mb-0">Horários de funcionamento</h1>
  </div>
</section>

<div class="card">
  <div class="card-body p-4">
    <form method="post" action="<?= e(url('/painel/horarios')) ?>">
      <?= Csrf::field() ?>
      <div class="d-grid gap-3">
        <?php foreach ($days as $weekday => $label):
          $row = $hours[$weekday] ?? null;
          $closed = $row ? (int) $row['is_closed'] === 1 : $weekday === 7;
          $opens = $row['opens_at'] ?? '09:00:00';
          $closes = $row['closes_at'] ?? ($weekday === 6 ? '14:00:00' : '18:00:00');
        ?>
          <div class="row g-3 align-items-center border-bottom pb-3">
            <div class="col-md-4 fw-semibold"><?= e($label) ?></div>
            <div class="col-6 col-md-3">
              <label class="form-label small text-muted-app mb-1">Abre</label>
              <input class="form-control" type="time" name="opens[<?= $weekday ?>]" value="<?= e(substr((string) $opens, 0, 5)) ?>">
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label small text-muted-app mb-1">Fecha</label>
              <input class="form-control" type="time" name="closes[<?= $weekday ?>]" value="<?= e(substr((string) $closes, 0, 5)) ?>">
            </div>
            <div class="col-md-2">
              <div class="form-check form-switch mt-md-4">
                <input class="form-check-input" type="checkbox" name="closed[<?= $weekday ?>]" id="closed-<?= $weekday ?>" <?= $closed ? 'checked' : '' ?>>
                <label class="form-check-label small" for="closed-<?= $weekday ?>">Fechado</label>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-4 d-flex justify-content-end"><button class="btn btn-dark px-4">Salvar horários</button></div>
    </form>
  </div>
</div>
