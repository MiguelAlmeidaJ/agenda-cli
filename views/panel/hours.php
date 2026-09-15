<?php

use App\Core\Csrf;

$days = [
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    7 => 'Domingo',
];
?>
<section class="page-header schedule-page-header">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
    <div>
      <div class="small text-muted-app mb-1">Configuração</div>
      <h1 class="h3 mb-1">Horários e funcionamento</h1>
      <p class="text-muted-app mb-0">Defina sua semana padrão e ajuste feriados ou dias excepcionais sem alterar a rotina.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <form method="post" action="<?= e(url('/painel/horarios/especiais')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="date" value="<?= e($today) ?>">
        <input type="hidden" name="name" value="Fechamento emergencial">
        <input type="hidden" name="source" value="emergency">
        <input type="hidden" name="mode" value="closed">
        <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-circle me-1"></i>Fechar hoje</button>
      </form>
      <form method="post" action="<?= e(url('/painel/horarios/especiais')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="date" value="<?= e($tomorrow) ?>">
        <input type="hidden" name="name" value="Fechamento excepcional">
        <input type="hidden" name="source" value="emergency">
        <input type="hidden" name="mode" value="closed">
        <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-calendar-x me-1"></i>Fechar amanhã</button>
      </form>
    </div>
  </div>
</section>

<div class="schedule-note mb-4">
  <i class="bi bi-info-circle"></i>
  <div>
    <strong>Datas especiais têm prioridade.</strong>
    <span>Fechar um dia impede novos agendamentos, mas não cancela automaticamente horários já confirmados.</span>
  </div>
</div>

<div class="card schedule-card mb-4">
  <div class="card-header bg-white schedule-card-header">
    <div>
      <h2 class="h5 mb-1">Semana padrão</h2>
      <p class="small text-muted-app mb-0">Esse horário se repete toda semana quando não houver uma exceção cadastrada.</p>
    </div>
  </div>
  <div class="card-body p-0">
    <form method="post" action="<?= e(url('/painel/horarios')) ?>">
      <?= Csrf::field() ?>
      <div class="schedule-week-list">
        <?php foreach ($days as $weekday => $label):
          $row = $hours[$weekday] ?? null;
          $closed = $row ? (int) $row['is_closed'] === 1 : $weekday === 7;
          $opens = $row['opens_at'] ?? '09:00:00';
          $closes = $row['closes_at'] ?? ($weekday === 6 ? '14:00:00' : '18:00:00');
        ?>
          <div class="schedule-week-row" data-week-row>
            <div class="schedule-day">
              <strong><?= e($label) ?></strong>
              <span class="small text-muted-app schedule-day-summary">
                <?= $closed ? 'Fechado' : e(substr((string) $opens, 0, 5) . ' às ' . substr((string) $closes, 0, 5)) ?>
              </span>
            </div>
            <div class="schedule-time-field">
              <label class="form-label small text-muted-app mb-1">Abre</label>
              <input class="form-control" type="time" name="opens[<?= $weekday ?>]" value="<?= e(substr((string) $opens, 0, 5)) ?>" <?= $closed ? 'disabled' : '' ?> data-time-input>
            </div>
            <div class="schedule-time-field">
              <label class="form-label small text-muted-app mb-1">Fecha</label>
              <input class="form-control" type="time" name="closes[<?= $weekday ?>]" value="<?= e(substr((string) $closes, 0, 5)) ?>" <?= $closed ? 'disabled' : '' ?> data-time-input>
            </div>
            <div class="schedule-closed-toggle">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="closed[<?= $weekday ?>]" id="closed-<?= $weekday ?>" <?= $closed ? 'checked' : '' ?> data-closed-toggle>
                <label class="form-check-label small" for="closed-<?= $weekday ?>">Fechado</label>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="p-4 border-top d-flex justify-content-end">
        <button class="btn btn-dark px-4"><i class="bi bi-check2 me-1"></i>Salvar semana</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-7">
    <div class="card schedule-card h-100">
      <div class="card-header bg-white schedule-card-header">
        <div>
          <h2 class="h5 mb-1">Datas especiais</h2>
          <p class="small text-muted-app mb-0">Fechamentos, horários reduzidos, feriados locais, férias ou eventos.</p>
        </div>
      </div>
      <div class="card-body p-0">
        <?php if ($specialDates === []): ?>
          <div class="schedule-empty">
            <i class="bi bi-calendar2-week"></i>
            <strong>Nenhuma exceção cadastrada</strong>
            <span>Quando algo fugir da rotina semanal, adicione ao lado.</span>
          </div>
        <?php else: ?>
          <div class="special-date-list">
            <?php foreach ($specialDates as $special): ?>
              <div class="special-date-row">
                <div class="special-date-calendar">
                  <strong><?= e(date('d', strtotime((string) $special['special_date']))) ?></strong>
                  <span><?= e(strtoupper(date('M', strtotime((string) $special['special_date'])))) ?></span>
                </div>
                <div class="special-date-content">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <strong><?= e($special['name']) ?></strong>
                    <?php if (($special['source'] ?? '') === 'emergency'): ?><span class="badge text-bg-light border">Emergência</span><?php endif; ?>
                  </div>
                  <div class="small text-muted-app mt-1">
                    <?= e(date('d/m/Y', strtotime((string) $special['special_date']))) ?> ·
                    <?php if ((int) $special['is_closed'] === 1): ?>
                      <span class="text-danger">Fechado o dia todo</span>
                    <?php else: ?>
                      <?= e(substr((string) $special['opens_at'], 0, 5)) ?> às <?= e(substr((string) $special['closes_at'], 0, 5)) ?>
                    <?php endif; ?>
                  </div>
                </div>
                <form method="post" action="<?= e(url('/painel/horarios/especiais/' . $special['id'] . '/remover')) ?>">
                  <?= Csrf::field() ?>
                  <button class="btn btn-sm btn-light border" type="submit" title="Remover exceção"><i class="bi bi-trash"></i></button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card schedule-card h-100">
      <div class="card-body p-4">
        <div class="icon-box icon-box-sm mb-3"><i class="bi bi-calendar-plus"></i></div>
        <h2 class="h5 mb-1">Adicionar exceção</h2>
        <p class="small text-muted-app mb-4">Use para feriado municipal, manutenção, férias ou para fechar mais cedo.</p>

        <form method="post" action="<?= e(url('/painel/horarios/especiais')) ?>" data-special-form>
          <?= Csrf::field() ?>
          <input type="hidden" name="source" value="custom">
          <div class="mb-3">
            <label class="form-label">Data</label>
            <input class="form-control" type="date" name="date" min="<?= e($today) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Motivo ou nome</label>
            <input class="form-control" name="name" maxlength="150" placeholder="Ex.: Aniversário da cidade" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Funcionamento</label>
            <select class="form-select" name="mode" data-mode-select>
              <option value="closed">Fechado o dia todo</option>
              <option value="open">Horário especial</option>
            </select>
          </div>
          <div class="row g-3 mb-4 d-none" data-special-times>
            <div class="col-6">
              <label class="form-label">Abre</label>
              <input class="form-control" type="time" name="opens_at" value="09:00">
            </div>
            <div class="col-6">
              <label class="form-label">Fecha</label>
              <input class="form-control" type="time" name="closes_at" value="14:00">
            </div>
          </div>
          <button class="btn btn-dark w-100"><i class="bi bi-plus-lg me-1"></i>Adicionar data especial</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card schedule-card">
  <div class="card-header bg-white schedule-card-header">
    <div>
      <h2 class="h5 mb-1">Feriados nacionais</h2>
      <p class="small text-muted-app mb-0">Ficam fechados por padrão. Ajuste somente os feriados em que o estabelecimento trabalhará.</p>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="holiday-list">
      <?php foreach ($nationalHolidays as $index => $holiday):
        $override = $holiday['override'];
        $working = $override && (int) $override['is_closed'] === 0;
        $status = $working ? 'Horário especial' : 'Fechado';
      ?>
        <div class="holiday-row">
          <div class="holiday-date">
            <strong><?= e(date('d/m', strtotime($holiday['date']))) ?></strong>
            <span><?= e(date('Y', strtotime($holiday['date']))) ?></span>
          </div>
          <div class="holiday-main">
            <strong><?= e($holiday['name']) ?></strong>
            <div class="small mt-1 <?= $working ? 'text-success' : 'text-muted-app' ?>">
              <?php if ($working): ?>
                Aberto de <?= e(substr((string) $override['opens_at'], 0, 5)) ?> às <?= e(substr((string) $override['closes_at'], 0, 5)) ?>
              <?php elseif ($override): ?>
                Fechado por configuração
              <?php else: ?>
                Fechado por padrão
              <?php endif; ?>
            </div>
          </div>
          <div class="holiday-status"><span class="status-pill <?= $working ? 'status-confirmed' : 'status-cancelled' ?>"><?= e($status) ?></span></div>
          <button class="btn btn-sm btn-outline-secondary holiday-adjust" type="button" data-bs-toggle="collapse" data-bs-target="#holiday-<?= $index ?>" aria-expanded="false">Ajustar</button>
        </div>
        <div class="collapse holiday-editor" id="holiday-<?= $index ?>">
          <div class="holiday-editor-inner">
            <form method="post" action="<?= e(url('/painel/horarios/especiais')) ?>" class="row g-3 align-items-end" data-special-form>
              <?= Csrf::field() ?>
              <input type="hidden" name="date" value="<?= e($holiday['date']) ?>">
              <input type="hidden" name="name" value="<?= e($holiday['name']) ?>">
              <input type="hidden" name="source" value="national">
              <div class="col-md-4">
                <label class="form-label small">Neste feriado</label>
                <select class="form-select" name="mode" data-mode-select>
                  <option value="closed" <?= !$working ? 'selected' : '' ?>>Não trabalhar</option>
                  <option value="open" <?= $working ? 'selected' : '' ?>>Trabalhar em horário especial</option>
                </select>
              </div>
              <div class="col-md-4 <?= $working ? '' : 'd-none' ?>" data-special-times>
                <div class="row g-2">
                  <div class="col-6">
                    <label class="form-label small">Abre</label>
                    <input class="form-control" type="time" name="opens_at" value="<?= e($working ? substr((string) $override['opens_at'], 0, 5) : '09:00') ?>">
                  </div>
                  <div class="col-6">
                    <label class="form-label small">Fecha</label>
                    <input class="form-control" type="time" name="closes_at" value="<?= e($working ? substr((string) $override['closes_at'], 0, 5) : '14:00') ?>">
                  </div>
                </div>
              </div>
              <div class="col-md-auto ms-md-auto">
                <button class="btn btn-dark">Salvar ajuste</button>
              </div>
            </form>
            <?php if ($override): ?>
              <form method="post" action="<?= e(url('/painel/horarios/especiais/' . $override['id'] . '/remover')) ?>" class="mt-2">
                <?= Csrf::field() ?>
                <button class="btn btn-link btn-sm text-muted p-0" type="submit">Restaurar padrão do feriado</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('[data-week-row]').forEach(row => {
  const toggle = row.querySelector('[data-closed-toggle]');
  const inputs = row.querySelectorAll('[data-time-input]');
  const summary = row.querySelector('.schedule-day-summary');

  function syncWeekRow() {
    inputs.forEach(input => input.disabled = toggle.checked);
    if (toggle.checked) {
      summary.textContent = 'Fechado';
    } else {
      summary.textContent = `${inputs[0].value} às ${inputs[1].value}`;
    }
  }
  toggle.addEventListener('change', syncWeekRow);
  inputs.forEach(input => input.addEventListener('change', syncWeekRow));
});

document.querySelectorAll('[data-special-form]').forEach(form => {
  const mode = form.querySelector('[data-mode-select]');
  const times = form.querySelector('[data-special-times]');
  if (!mode || !times) return;

  function syncSpecialTimes() {
    times.classList.toggle('d-none', mode.value !== 'open');
    times.querySelectorAll('input').forEach(input => input.required = mode.value === 'open');
  }
  mode.addEventListener('change', syncSpecialTimes);
  syncSpecialTimes();
});
</script>
