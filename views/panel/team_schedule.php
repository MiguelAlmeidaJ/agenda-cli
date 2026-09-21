<?php

use App\Core\Csrf;

$days = [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'];
$today = (string) $today;
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
              $businessRanges = $businessHourRanges[$weekday] ?? [];
              $mode = !$row ? 'inherit' : ((int) $row['is_off'] === 1 ? 'off' : 'custom');
              $businessText = (!$business || (int) $business['is_closed'] === 1)
                  ? 'Estabelecimento fechado'
                  : implode(' / ', array_map(
                      static fn (array $range): string => substr((string) $range['opens_at'], 0, 5) . '–' . substr((string) $range['closes_at'], 0, 5),
                      $businessRanges
                  ));
              $ranges = $providerHourRanges[$weekday] ?? [];
              if ($mode === 'custom' && $ranges === []) {
                  $ranges = [[
                      'opens_at' => $row['opens_at'] ? substr((string) $row['opens_at'], 0, 5) : '09:00',
                      'closes_at' => $row['closes_at'] ? substr((string) $row['closes_at'], 0, 5) : '18:00',
                  ]];
              }
              if ($ranges === []) {
                  $ranges = $businessRanges !== [] ? $businessRanges : [['opens_at' => '09:00', 'closes_at' => '18:00']];
              }
            ?>
              <div class="border rounded-3 p-3" data-provider-day="<?= $weekday ?>">
                <div class="row g-3">
                  <div class="col-xl-3">
                    <div class="fw-semibold"><?= e($label) ?></div>
                    <div class="small text-muted-app mt-1"><?= e($businessText) ?></div>
                  </div>
                  <div class="col-xl-3">
                    <label class="form-label small text-muted-app mb-1">Regra</label>
                    <select class="form-select provider-mode" name="mode[<?= $weekday ?>]" data-day="<?= $weekday ?>">
                      <option value="inherit" <?= $mode === 'inherit' ? 'selected' : '' ?>>Herdar estabelecimento</option>
                      <option value="custom" <?= $mode === 'custom' ? 'selected' : '' ?>>Horário próprio</option>
                      <option value="off" <?= $mode === 'off' ? 'selected' : '' ?>>Folga</option>
                    </select>
                  </div>
                  <div class="col-xl-6">
                    <div class="d-grid gap-2" data-provider-range-list="<?= $weekday ?>">
                      <?php foreach ($ranges as $index => $range): ?>
                        <div class="row g-2 align-items-end" data-provider-range-row>
                          <div class="col-5">
                            <label class="form-label small text-muted-app mb-1">Início</label>
                            <input class="form-control" type="time" name="ranges[<?= $weekday ?>][<?= $index ?>][opens]" value="<?= e(substr((string) $range['opens_at'], 0, 5)) ?>">
                          </div>
                          <div class="col-5">
                            <label class="form-label small text-muted-app mb-1">Fim</label>
                            <input class="form-control" type="time" name="ranges[<?= $weekday ?>][<?= $index ?>][closes]" value="<?= e(substr((string) $range['closes_at'], 0, 5)) ?>">
                          </div>
                          <div class="col-2">
                            <button class="btn btn-outline-danger w-100" type="button" data-remove-provider-range><i class="bi bi-trash"></i></button>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary mt-2" type="button" data-add-provider-range="<?= $weekday ?>"><i class="bi bi-plus-lg me-1"></i>Adicionar faixa</button>
                  </div>
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
    <div class="card mt-4">
      <div class="card-header bg-white p-4 border-bottom">
        <h2 class="h5 mb-1">Férias e ausências recorrentes</h2>
        <p class="small text-muted-app mb-0">Bloqueie vários dias de uma vez ou crie uma indisponibilidade que se repete toda semana.</p>
      </div>
      <div class="card-body p-4">
        <div class="row g-4">
          <div class="col-xl-6">
            <div class="border rounded-3 p-3 h-100">
              <div class="fw-semibold mb-1">Férias / período integral</div>
              <p class="small text-muted-app">O profissional ficará indisponível durante todo o dia entre as datas informadas.</p>
              <form method="post" action="<?= e(url('/painel/equipe/' . $provider['id'] . '/ausencias')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="absence_type" value="date_range">
                <div class="row g-2 mb-3">
                  <div class="col-sm-6">
                    <label class="form-label">De</label>
                    <input class="form-control" type="date" name="starts_on" min="<?= e($today) ?>" required>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label">Até</label>
                    <input class="form-control" type="date" name="ends_on" min="<?= e($today) ?>" required>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Motivo <span class="text-muted-app fw-normal">(opcional)</span></label>
                  <input class="form-control" name="reason" maxlength="190" placeholder="Ex.: férias, congresso">
                </div>
                <button class="btn btn-outline-dark w-100" type="submit"><i class="bi bi-calendar2-x me-2"></i>Adicionar período</button>
              </form>
            </div>
          </div>

          <div class="col-xl-6">
            <div class="border rounded-3 p-3 h-100">
              <div class="fw-semibold mb-1">Ausência semanal</div>
              <p class="small text-muted-app">Ex.: toda terça-feira, das 12:00 às 14:00.</p>
              <form method="post" action="<?= e(url('/painel/equipe/' . $provider['id'] . '/ausencias')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="absence_type" value="weekly">
                <div class="row g-2 mb-3">
                  <div class="col-sm-6">
                    <label class="form-label">Dia</label>
                    <select class="form-select" name="weekday" required>
                      <?php foreach ($days as $weekday => $label): ?>
                        <option value="<?= $weekday ?>"><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label">A partir de</label>
                    <input class="form-control" type="date" name="starts_on" min="<?= e($today) ?>" value="<?= e($today) ?>" required>
                  </div>
                </div>
                <div class="row g-2 mb-3">
                  <div class="col-sm-6">
                    <label class="form-label">De</label>
                    <input class="form-control" type="time" name="starts_at" required>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label">Até</label>
                    <input class="form-control" type="time" name="ends_at" required>
                  </div>
                </div>
                <div class="row g-2 mb-3">
                  <div class="col-sm-6">
                    <label class="form-label">Vigência até <span class="text-muted-app fw-normal">(opcional)</span></label>
                    <input class="form-control" type="date" name="ends_on" min="<?= e($today) ?>">
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label">Motivo <span class="text-muted-app fw-normal">(opcional)</span></label>
                    <input class="form-control" name="reason" maxlength="190" placeholder="Ex.: almoço fixo">
                  </div>
                </div>
                <button class="btn btn-outline-dark w-100" type="submit"><i class="bi bi-arrow-repeat me-2"></i>Adicionar recorrência</button>
              </form>
            </div>
          </div>
        </div>

        <div class="border-top mt-4 pt-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h6 mb-0">Ausências ativas e futuras</h3>
            <span class="small text-muted-app"><?= count($absences) ?> regra(s)</span>
          </div>
          <div class="d-grid gap-2">
            <?php foreach ($absences as $absence): ?>
              <div class="border rounded-3 p-3 d-flex justify-content-between align-items-start gap-3">
                <div>
                  <?php if ($absence['kind'] === 'date_range'): ?>
                    <div class="fw-semibold small">Férias / ausência integral</div>
                    <div class="small text-muted-app">
                      <?= e(date('d/m/Y', strtotime((string) $absence['starts_on']))) ?>
                      até <?= e(date('d/m/Y', strtotime((string) $absence['ends_on']))) ?>
                    </div>
                  <?php else: ?>
                    <div class="fw-semibold small">Toda <?= e(mb_strtolower($days[(int) $absence['weekday']] ?? 'semana')) ?></div>
                    <div class="small text-muted-app">
                      <?= e(substr((string) $absence['starts_at'], 0, 5)) ?>–<?= e(substr((string) $absence['ends_at'], 0, 5)) ?>
                      · desde <?= e(date('d/m/Y', strtotime((string) $absence['starts_on']))) ?>
                      <?php if (!empty($absence['ends_on'])): ?> · até <?= e(date('d/m/Y', strtotime((string) $absence['ends_on']))) ?><?php endif; ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($absence['reason'])): ?><div class="small mt-1"><?= e($absence['reason']) ?></div><?php endif; ?>
                </div>
                <form method="post" action="<?= e(url('/painel/equipe/' . $provider['id'] . '/ausencias/' . $absence['id'] . '/remover')) ?>">
                  <?= Csrf::field() ?>
                  <button class="btn btn-sm btn-link text-danger text-decoration-none" type="submit" aria-label="Remover ausência"><i class="bi bi-trash"></i></button>
                </form>
              </div>
            <?php endforeach; ?>
            <?php if ($absences === []): ?>
              <div class="border rounded-3 p-4 text-center small text-muted-app">Nenhuma ausência recorrente ou período de férias cadastrado.</div>
            <?php endif; ?>
          </div>
        </div>
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
(() => {
  function providerRangeMarkup(day, index) {
    return `
      <div class="row g-2 align-items-end" data-provider-range-row>
        <div class="col-5">
          <label class="form-label small text-muted-app mb-1">Início</label>
          <input class="form-control" type="time" name="ranges[${day}][${index}][opens]" value="09:00">
        </div>
        <div class="col-5">
          <label class="form-label small text-muted-app mb-1">Fim</label>
          <input class="form-control" type="time" name="ranges[${day}][${index}][closes]" value="18:00">
        </div>
        <div class="col-2">
          <button class="btn btn-outline-danger w-100" type="button" data-remove-provider-range><i class="bi bi-trash"></i></button>
        </div>
      </div>`;
  }

  function refreshProviderDay(container) {
    const mode = container.querySelector('.provider-mode').value;
    const enabled = mode === 'custom';
    container.querySelectorAll('[data-provider-range-row] input, [data-remove-provider-range]').forEach(element => {
      element.disabled = !enabled;
    });
    container.querySelector('[data-add-provider-range]').disabled = !enabled;
  }

  document.querySelectorAll('[data-provider-day]').forEach(container => {
    container.querySelector('.provider-mode').addEventListener('change', () => refreshProviderDay(container));
    container.addEventListener('click', event => {
      const remove = event.target.closest('[data-remove-provider-range]');
      if (remove) {
        const list = container.querySelector('[data-provider-range-list]');
        if (list.querySelectorAll('[data-provider-range-row]').length > 1) {
          remove.closest('[data-provider-range-row]').remove();
        }
        return;
      }

      const add = event.target.closest('[data-add-provider-range]');
      if (!add) return;
      const list = container.querySelector('[data-provider-range-list]');
      if (list.querySelectorAll('[data-provider-range-row]').length >= 8) return;
      list.insertAdjacentHTML('beforeend', providerRangeMarkup(container.dataset.providerDay, Date.now()));
      refreshProviderDay(container);
    });
    refreshProviderDay(container);
  });
})();
</script>
