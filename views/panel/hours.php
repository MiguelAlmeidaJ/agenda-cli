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
          $ranges = $hourRanges[$weekday] ?? [];
          if (!$closed && $ranges === []) {
              $ranges = [[
                  'opens_at' => '09:00',
                  'closes_at' => $weekday === 6 ? '14:00' : '18:00',
              ]];
          }
          $summary = $closed
              ? 'Fechado'
              : implode(' / ', array_map(
                  static fn (array $range): string => substr((string) $range['opens_at'], 0, 5) . '–' . substr((string) $range['closes_at'], 0, 5),
                  $ranges
              ));
        ?>
          <div class="p-4 border-bottom" data-week-row data-day="<?= $weekday ?>">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
              <div style="min-width: 180px">
                <strong><?= e($label) ?></strong>
                <div class="small text-muted-app mt-1" data-day-summary><?= e($summary) ?></div>
                <div class="form-check form-switch mt-2">
                  <input class="form-check-input" type="checkbox" name="closed[<?= $weekday ?>]" id="closed-<?= $weekday ?>" <?= $closed ? 'checked' : '' ?> data-closed-toggle>
                  <label class="form-check-label small" for="closed-<?= $weekday ?>">Fechado</label>
                </div>
              </div>

              <div class="flex-grow-1">
                <div class="d-grid gap-2" data-range-list="<?= $weekday ?>">
                  <?php foreach ($ranges as $index => $range): ?>
                    <div class="row g-2 align-items-end" data-range-row>
                      <div class="col-sm-5">
                        <label class="form-label small text-muted-app mb-1">Abre</label>
                        <input class="form-control" type="time" name="ranges[<?= $weekday ?>][<?= $index ?>][opens]" value="<?= e(substr((string) $range['opens_at'], 0, 5)) ?>" <?= $closed ? 'disabled' : '' ?> data-range-open>
                      </div>
                      <div class="col-sm-5">
                        <label class="form-label small text-muted-app mb-1">Fecha</label>
                        <input class="form-control" type="time" name="ranges[<?= $weekday ?>][<?= $index ?>][closes]" value="<?= e(substr((string) $range['closes_at'], 0, 5)) ?>" <?= $closed ? 'disabled' : '' ?> data-range-close>
                      </div>
                      <div class="col-sm-2">
                        <button class="btn btn-outline-danger w-100" type="button" data-remove-range <?= $closed ? 'disabled' : '' ?> title="Remover faixa"><i class="bi bi-trash"></i></button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <button class="btn btn-sm btn-outline-secondary mt-2" type="button" data-add-range="<?= $weekday ?>" <?= $closed ? 'disabled' : '' ?>><i class="bi bi-plus-lg me-1"></i>Adicionar faixa</button>
                <div class="form-text">Ex.: 08:00–12:00 e 14:00–18:00 para criar uma pausa recorrente.</div>
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
(() => {
  function rangeMarkup(day, index) {
    return `
      <div class="row g-2 align-items-end" data-range-row>
        <div class="col-sm-5">
          <label class="form-label small text-muted-app mb-1">Abre</label>
          <input class="form-control" type="time" name="ranges[${day}][${index}][opens]" value="09:00" data-range-open>
        </div>
        <div class="col-sm-5">
          <label class="form-label small text-muted-app mb-1">Fecha</label>
          <input class="form-control" type="time" name="ranges[${day}][${index}][closes]" value="18:00" data-range-close>
        </div>
        <div class="col-sm-2">
          <button class="btn btn-outline-danger w-100" type="button" data-remove-range title="Remover faixa"><i class="bi bi-trash"></i></button>
        </div>
      </div>`;
  }

  function refreshDay(dayRow) {
    const closed = dayRow.querySelector('[data-closed-toggle]').checked;
    const inputs = dayRow.querySelectorAll('[data-range-row] input');
    const removeButtons = dayRow.querySelectorAll('[data-remove-range]');
    const addButton = dayRow.querySelector('[data-add-range]');

    inputs.forEach(input => input.disabled = closed);
    removeButtons.forEach(button => button.disabled = closed);
    addButton.disabled = closed;

    const ranges = [...dayRow.querySelectorAll('[data-range-row]')].map(row => {
      const opens = row.querySelector('[data-range-open]').value;
      const closes = row.querySelector('[data-range-close]').value;
      return opens && closes ? `${opens}–${closes}` : '';
    }).filter(Boolean);

    dayRow.querySelector('[data-day-summary]').textContent = closed
      ? 'Fechado'
      : (ranges.join(' / ') || 'Adicione uma faixa');
  }

  document.querySelectorAll('[data-week-row]').forEach(dayRow => {
    dayRow.querySelector('[data-closed-toggle]').addEventListener('change', () => refreshDay(dayRow));
    dayRow.addEventListener('change', event => {
      if (event.target.matches('[data-range-open], [data-range-close]')) refreshDay(dayRow);
    });
    dayRow.addEventListener('click', event => {
      const remove = event.target.closest('[data-remove-range]');
      if (remove) {
        const list = dayRow.querySelector('[data-range-list]');
        if (list.querySelectorAll('[data-range-row]').length > 1) {
          remove.closest('[data-range-row]').remove();
          refreshDay(dayRow);
        }
        return;
      }

      const add = event.target.closest('[data-add-range]');
      if (!add) return;
      const list = dayRow.querySelector('[data-range-list]');
      if (list.querySelectorAll('[data-range-row]').length >= 8) return;
      list.insertAdjacentHTML('beforeend', rangeMarkup(dayRow.dataset.day, Date.now()));
      refreshDay(dayRow);
    });
    refreshDay(dayRow);
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
})();
</script>
