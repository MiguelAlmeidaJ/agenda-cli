<?php

use App\Core\Csrf;

$days = [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'];
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
?>
<section class="page-header">
  <a class="small text-decoration-none text-muted-app" href="<?= e(url('/painel/equipe')) ?>"><i class="bi bi-arrow-left me-1"></i>Voltar para equipe</a>
  <div class="mt-3 d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <div class="small text-muted-app mb-1">Agenda individual</div>
      <h1 class="h3 mb-1"><?= e($provider['name']) ?></h1>
      <p class="text-muted-app mb-0">Defina quando este profissional atende e bloqueie períodos específicos.</p>
    </div>
  </div>
</section>

<div class="row g-4 align-items-start">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header bg-white p-4 border-bottom">
        <h2 class="h5 mb-1">Jornada semanal</h2>
        <p class="small text-muted-app mb-0">Por padrão, o profissional herda o horário do estabelecimento.</p>
      </div>
      <div class="card-body p-4">
        <form method="post" action="<?= e(url('/painel/equipe/' . $provider['id'] . '/horarios')) ?>">
          <?= Csrf::field() ?>
          <div class="d-grid gap-3">
            <?php foreach ($days as $weekday => $label):
              $row = $hours[$weekday] ?? null;
              $business = $businessHours[$weekday] ?? null;
              $mode = !$row ? 'inherit' : ((int) $row['is_off'] === 1 ? 'off' : 'custom');
              $businessText = (!$business || (int) $business['is_closed'] === 1)
                  ? 'Estabelecimento fechado'
                  : substr((string) $business['opens_at'], 0, 5) . '–' . substr((string) $business['closes_at'], 0, 5);
              $opens = $row['opens_at'] ?? $business['opens_at'] ?? '09:00:00';
              $closes = $row['closes_at'] ?? $business['closes_at'] ?? '18:00:00';
            ?>
              <div class="provider-hours-row">
                <div>
                  <div class="fw-semibold"><?= e($label) ?></div>
                  <div class="small text-muted-app"><?= e($businessText) ?></div>
                </div>
                <div>
                  <label class="form-label small text-muted-app mb-1">Regra</label>
                  <select class="form-select provider-mode" name="mode[<?= $weekday ?>]" data-day="<?= $weekday ?>">
                    <option value="inherit" <?= $mode === 'inherit' ? 'selected' : '' ?>>Herdar estabelecimento</option>
                    <option value="custom" <?= $mode === 'custom' ? 'selected' : '' ?>>Horário próprio</option>
                    <option value="off" <?= $mode === 'off' ? 'selected' : '' ?>>Folga</option>
                  </select>
                </div>
                <div class="provider-time-fields" data-time-fields="<?= $weekday ?>">
                  <label class="form-label small text-muted-app mb-1">Início</label>
                  <input class="form-control" type="time" name="opens[<?= $weekday ?>]" value="<?= e(substr((string) $opens, 0, 5)) ?>">
                </div>
                <div class="provider-time-fields" data-time-fields="<?= $weekday ?>">
                  <label class="form-label small text-muted-app mb-1">Fim</label>
                  <input class="form-control" type="time" name="closes[<?= $weekday ?>]" value="<?= e(substr((string) $closes, 0, 5)) ?>">
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-4 d-flex justify-content-end">
            <button class="btn btn-dark px-4" type="submit">Salvar jornada</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-4">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Bloquear período</h2>
        <p class="small text-muted-app mb-4">Use para almoço, compromisso, consulta, saída antecipada ou outra indisponibilidade.</p>
        <form method="post" action="<?= e(url('/painel/equipe/' . $provider['id'] . '/bloqueios')) ?>">
          <?= Csrf::field() ?>
          <div class="mb-3">
            <label class="form-label">Data</label>
            <input class="form-control" type="date" name="date" min="<?= e($today) ?>" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">De</label>
              <input class="form-control" type="time" name="starts_at" required>
            </div>
            <div class="col-6">
              <label class="form-label">Até</label>
              <input class="form-control" type="time" name="ends_at" required>
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label">Motivo <span class="text-muted-app fw-normal">(opcional)</span></label>
            <input class="form-control" name="reason" maxlength="190" placeholder="Ex.: almoço, consulta">
          </div>
          <button class="btn btn-outline-dark w-100" type="submit"><i class="bi bi-slash-circle me-2"></i>Adicionar bloqueio</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header bg-white p-4 border-bottom">
        <h2 class="h6 mb-0">Próximos bloqueios</h2>
      </div>
      <div class="card-body p-0">
        <?php foreach ($blocks as $block): ?>
          <div class="schedule-block-row">
            <div>
              <div class="fw-semibold small"><?= e(date('d/m/Y', strtotime((string) $block['starts_at']))) ?></div>
              <div class="small text-muted-app"><?= e(date('H:i', strtotime((string) $block['starts_at']))) ?>–<?= e(date('H:i', strtotime((string) $block['ends_at']))) ?></div>
              <?php if ($block['reason']): ?><div class="small mt-1"><?= e($block['reason']) ?></div><?php endif; ?>
            </div>
            <form method="post" action="<?= e(url('/painel/equipe/' . $provider['id'] . '/bloqueios/' . $block['id'] . '/remover')) ?>">
              <?= Csrf::field() ?>
              <button class="btn btn-sm btn-link text-danger text-decoration-none" type="submit" aria-label="Remover bloqueio"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        <?php endforeach; ?>
        <?php if ($blocks === []): ?>
          <div class="p-4 text-center small text-muted-app">Nenhum bloqueio futuro.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function updateProviderHourFields() {
  document.querySelectorAll('.provider-mode').forEach(select => {
    const day = select.dataset.day;
    document.querySelectorAll(`[data-time-fields="${day}"] input`).forEach(input => {
      input.disabled = select.value !== 'custom';
    });
  });
}
document.querySelectorAll('.provider-mode').forEach(select => select.addEventListener('change', updateProviderHourFields));
updateProviderHourFields();
</script>
