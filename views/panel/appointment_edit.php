<?php

use App\Core\Csrf;

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$currentDate = date('Y-m-d', strtotime((string) $appointment['starts_at']));
$currentTime = date('H:i', strtotime((string) $appointment['starts_at']));
$statusLabels = [
    'pending' => 'Pendente',
    'confirmed' => 'Confirmado',
    'completed' => 'Concluído',
    'cancelled' => 'Cancelado',
    'no_show' => 'Não compareceu',
];
$eventLabels = [
    'status_changed' => 'Status alterado',
    'rescheduled' => 'Reagendado',
];
$canReschedule = in_array($appointment['status'], ['pending', 'confirmed'], true);
?>
<section class="page-header">
  <a class="small text-decoration-none text-muted-app" href="<?= e(url('/painel/agendamentos')) ?>"><i class="bi bi-arrow-left me-1"></i>Voltar para agendamentos</a>
  <div class="mt-3 d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <div class="small text-muted-app mb-1">Agendamento #<?= (int) $appointment['id'] ?></div>
      <h1 class="h3 mb-1"><?= e($appointment['client_name']) ?></h1>
      <div class="text-muted-app"><?= e($appointment['service_name']) ?> · <?= (int) $appointment['duration_minutes'] ?> min</div>
    </div>
    <span class="status-pill status-<?= e($appointment['status']) ?>"><?= e($statusLabels[$appointment['status']] ?? $appointment['status']) ?></span>
  </div>
</section>

<div class="row g-4 align-items-start">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header bg-white p-4 border-bottom">
        <h2 class="h5 mb-1">Reagendar atendimento</h2>
        <p class="small text-muted-app mb-0">Troque profissional, data ou horário sem criar um novo agendamento.</p>
      </div>
      <div class="card-body p-4">
        <?php if ($canReschedule): ?>
          <form method="post" action="<?= e(url('/painel/agendamentos/' . $appointment['id'] . '/editar')) ?>" id="reschedule-form">
            <?= Csrf::field() ?>
            <div class="mb-3">
              <label class="form-label">Profissional</label>
              <select class="form-select" name="employee_id" id="reschedule-provider" required>
                <?php foreach ($providers as $provider): ?>
                  <option value="<?= (int) $provider['id'] ?>" <?= (int) $provider['id'] === (int) $appointment['employee_user_id'] ? 'selected' : '' ?>><?= e($provider['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Data</label>
              <input class="form-control" type="date" name="date" id="reschedule-date" min="<?= e($today) ?>" value="<?= e(max($currentDate, $today)) ?>" required>
            </div>
            <div class="mb-4">
              <label class="form-label">Horário</label>
              <div class="slot-grid" id="reschedule-slots"><span class="small text-muted-app">Carregando horários...</span></div>
            </div>
            <button class="btn btn-dark" type="submit"><i class="bi bi-calendar-event me-2"></i>Salvar novo horário</button>
          </form>
        <?php else: ?>
          <div class="alert alert-light border mb-0">Agendamentos concluídos, cancelados ou marcados como falta não podem ser reagendados.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-4">
      <div class="card-body p-4">
        <h2 class="h6 mb-3">Resumo</h2>
        <div class="appointment-summary-list">
          <div><span>Data atual</span><strong><?= e(date('d/m/Y', strtotime((string) $appointment['starts_at']))) ?> às <?= e($currentTime) ?></strong></div>
          <div><span>Profissional</span><strong><?= e($appointment['employee_name']) ?></strong></div>
          <div><span>Serviço</span><strong><?= e($appointment['service_name']) ?></strong></div>
          <div><span>Valor</span><strong>R$ <?= e(number_format((float) $appointment['price'], 2, ',', '.')) ?></strong></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header bg-white p-4 border-bottom">
        <h2 class="h6 mb-0">Histórico</h2>
      </div>
      <div class="card-body p-0">
        <?php foreach ($events as $event): ?>
          <div class="appointment-history-row">
            <div class="appointment-history-dot"></div>
            <div>
              <div class="small fw-semibold"><?= e($eventLabels[$event['event_type']] ?? $event['event_type']) ?></div>
              <?php if ($event['from_status'] || $event['to_status']): ?>
                <div class="small text-muted-app"><?= e($statusLabels[$event['from_status']] ?? $event['from_status']) ?> → <?= e($statusLabels[$event['to_status']] ?? $event['to_status']) ?></div>
              <?php endif; ?>
              <?php if ($event['details']): ?><div class="small text-muted-app"><?= e($event['details']) ?></div><?php endif; ?>
              <div class="small text-muted-app mt-1"><?= e(date('d/m/Y H:i', strtotime((string) $event['created_at']))) ?><?= $event['user_name'] ? ' · ' . e($event['user_name']) : '' ?></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if ($events === []): ?>
          <div class="p-4 text-center small text-muted-app">Nenhuma alteração registrada ainda.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($canReschedule): ?>
<script>
const provider = document.getElementById('reschedule-provider');
const dateInput = document.getElementById('reschedule-date');
const slots = document.getElementById('reschedule-slots');
const availabilityUrl = <?= json_encode(url('/painel/agendamentos/' . $appointment['id'] . '/horarios')) ?>;
const currentProvider = <?= (int) $appointment['employee_user_id'] ?>;
const currentDate = <?= json_encode($currentDate) ?>;
const currentTime = <?= json_encode($currentTime) ?>;

provider.addEventListener('change', loadSlots);
dateInput.addEventListener('change', loadSlots);
loadSlots();

async function loadSlots() {
  if (!provider.value || !dateInput.value) return;
  slots.innerHTML = '<span class="small text-muted-app">Buscando horários disponíveis...</span>';
  const query = new URLSearchParams({employee_id: provider.value, date: dateInput.value});
  try {
    const response = await fetch(`${availabilityUrl}?${query.toString()}`, {headers: {'Accept': 'application/json'}});
    const data = await response.json();
    if (!data.slots || !data.slots.length) {
      slots.innerHTML = '<span class="small text-muted-app">Nenhum horário disponível nesta data.</span>';
      return;
    }
    slots.innerHTML = data.slots.map((time, index) => {
      const checked = Number(provider.value) === currentProvider && dateInput.value === currentDate && time === currentTime ? 'checked' : '';
      return `<span><input class="slot-input" type="radio" name="time" id="reschedule-slot-${index}" value="${time}" ${checked} required><label class="slot-label" for="reschedule-slot-${index}">${time}</label></span>`;
    }).join('');
  } catch (_) {
    slots.innerHTML = '<span class="small text-danger">Não foi possível carregar os horários.</span>';
  }
}
</script>
<?php endif; ?>
