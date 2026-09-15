<?php

use App\Core\Csrf;

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
?>
<section class="page-header">
  <a href="<?= e(url('/')) ?>" class="small text-decoration-none text-muted-app"><i class="bi bi-arrow-left me-1"></i>Voltar</a>
  <div class="row g-4 mt-1 align-items-start">
    <div class="col-lg-8">
      <h1 class="h2 mb-2"><?= e($establishment['name']) ?></h1>
      <p class="text-muted-app mb-2"><?= e($establishment['description'] ?? '') ?></p>
      <div class="small text-muted-app">
        <?php if ($establishment['address_line']): ?><i class="bi bi-geo-alt me-1"></i><?= e($establishment['address_line']) ?>, <?= e($establishment['city']) ?> - <?= e($establishment['state']) ?><?php endif; ?>
      </div>
    </div>
  </div>
</section>

<div class="row g-4">
  <div class="col-lg-7">
    <h2 class="h5 mb-3">Serviços</h2>
    <div class="d-grid gap-2">
      <?php foreach ($services as $service): ?>
        <div class="card">
          <div class="card-body p-4 d-flex justify-content-between gap-4">
            <div>
              <div class="fw-semibold mb-1"><?= e($service['name']) ?></div>
              <div class="small text-muted-app mb-2"><?= e($service['description'] ?? '') ?></div>
              <span class="small text-muted-app"><i class="bi bi-clock me-1"></i><?= (int) $service['duration_minutes'] ?> min</span>
            </div>
            <div class="service-price text-nowrap">R$ <?= e(number_format((float) $service['price'], 2, ',', '.')) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card sticky-lg-top" style="top: 88px">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Agendar horário</h2>
        <p class="small text-muted-app mb-4">Escolha o serviço, profissional e uma data.</p>

        <form method="post" action="<?= e(url('/estabelecimentos/' . $establishment['slug'] . '/agendar')) ?>" id="booking-form">
          <?= Csrf::field() ?>
          <div class="mb-3">
            <label class="form-label">Serviço</label>
            <select class="form-select" name="service_id" id="service" required>
              <option value="">Selecione</option>
              <?php foreach ($services as $service): ?>
                <option value="<?= (int) $service['id'] ?>"><?= e($service['name']) ?> · R$ <?= e(number_format((float) $service['price'], 2, ',', '.')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Profissional</label>
            <select class="form-select" name="employee_id" id="employee" required disabled>
              <option value="">Escolha um serviço primeiro</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Data</label>
            <input class="form-control" type="date" name="date" id="date" min="<?= e($today) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Horário</label>
            <div id="slots" class="slot-grid"><span class="small text-muted-app">Selecione serviço, profissional e data.</span></div>
          </div>

          <div class="mb-4">
            <label class="form-label">Observação <span class="text-muted-app fw-normal">(opcional)</span></label>
            <textarea class="form-control" rows="2" name="notes" maxlength="500"></textarea>
          </div>

          <button class="btn btn-dark w-100" type="submit"><i class="bi bi-calendar2-check me-2"></i>Confirmar agendamento</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
const employeesByService = <?= json_encode($employeesByService, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const availabilityUrl = <?= json_encode(url('/estabelecimentos/' . $establishment['slug'] . '/horarios')) ?>;
const service = document.getElementById('service');
const employee = document.getElementById('employee');
const date = document.getElementById('date');
const slots = document.getElementById('slots');

service.addEventListener('change', () => {
  const people = employeesByService[service.value] || [];
  employee.innerHTML = '<option value="">Selecione</option>' + people.map(person => `<option value="${person.id}">${escapeHtml(person.name)}</option>`).join('');
  employee.disabled = people.length === 0;
  if (people.length === 0) employee.innerHTML = '<option value="">Nenhum profissional disponível</option>';
  renderHint();
});

employee.addEventListener('change', loadSlots);
date.addEventListener('change', loadSlots);

async function loadSlots() {
  if (!service.value || !employee.value || !date.value) return renderHint();
  slots.innerHTML = '<span class="small text-muted-app">Buscando horários...</span>';
  const query = new URLSearchParams({service_id: service.value, employee_id: employee.value, date: date.value});
  try {
    const response = await fetch(`${availabilityUrl}?${query.toString()}`, {headers: {'Accept': 'application/json'}});
    const data = await response.json();
    if (!data.slots.length) {
      slots.innerHTML = '<span class="small text-muted-app">Sem horários disponíveis nesta data.</span>';
      return;
    }
    slots.innerHTML = data.slots.map((time, index) => `<span><input class="slot-input" type="radio" name="time" id="slot-${index}" value="${time}" required><label class="slot-label" for="slot-${index}">${time}</label></span>`).join('');
  } catch (_) {
    slots.innerHTML = '<span class="small text-danger">Não foi possível carregar os horários.</span>';
  }
}

function renderHint() {
  slots.innerHTML = '<span class="small text-muted-app">Selecione serviço, profissional e data.</span>';
}

function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = value;
  return div.innerHTML;
}
</script>
