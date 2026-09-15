<?php
use App\Core\Csrf;

$today = date('Y-m-d');
?>
<section class="page-header">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <a class="small text-decoration-none text-muted-app" href="<?= e(url('/painel/agenda')) ?>"><i class="bi bi-arrow-left me-1"></i>Voltar para agenda</a>
      <div class="small text-muted-app mt-3 mb-1">Recepção</div>
      <h1 class="h3 mb-1">Novo agendamento</h1>
      <p class="text-muted-app mb-0">Crie um horário usando a mesma disponibilidade da agenda online.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/painel/clientes')) ?>"><i class="bi bi-person-plus me-2"></i>Cadastrar cliente</a>
  </div>
</section>

<?php if ($customers === []): ?>
  <div class="alert alert-light border d-flex gap-3 align-items-start mb-4">
    <i class="bi bi-person-plus fs-5"></i>
    <div><strong>Cadastre um cliente primeiro.</strong><div class="small text-muted-app">O agendamento manual fica ligado ao histórico do cliente no estabelecimento.</div></div>
  </div>
<?php endif; ?>

<div class="row g-4 align-items-start">
  <div class="col-lg-7">
    <form method="post" action="<?= e(url('/painel/agendamentos/novo')) ?>" id="manual-booking-form" class="card crm-card">
      <div class="card-body p-4 p-xl-5">
        <?= Csrf::field() ?>
        <input type="hidden" name="employee_id" id="manual-employee-id">
        <input type="hidden" name="time" id="manual-time">

        <div class="manual-booking-step">
          <span class="booking-step-number">1</span>
          <div class="flex-grow-1">
            <label class="form-label fw-semibold" for="manual-customer">Cliente</label>
            <select class="form-select" name="customer_id" id="manual-customer" required <?= $customers === [] ? 'disabled' : '' ?>>
              <option value="">Selecione um cliente</option>
              <?php foreach ($customers as $customer): ?>
                <option value="<?= (int) $customer['id'] ?>" <?= (int) $selectedCustomerId === (int) $customer['id'] ? 'selected' : '' ?>>
                  <?= e($customer['name']) ?><?= !empty($customer['phone']) ? ' · ' . e($customer['phone']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Não encontrou? <a href="<?= e(url('/painel/clientes')) ?>">Cadastre o cliente</a> e volte para a agenda.</div>
          </div>
        </div>

        <div class="manual-booking-step">
          <span class="booking-step-number">2</span>
          <div class="flex-grow-1">
            <label class="form-label fw-semibold" for="manual-service">Serviço</label>
            <select class="form-select" name="service_id" id="manual-service" required>
              <option value="">Selecione o serviço</option>
              <?php foreach ($services as $service): ?>
                <option value="<?= (int) $service['id'] ?>" data-name="<?= e($service['name']) ?>" data-price="<?= e(number_format((float) $service['price'], 2, ',', '.')) ?>" data-duration="<?= (int) $service['duration_minutes'] ?>">
                  <?= e($service['name']) ?> · <?= (int) $service['duration_minutes'] ?> min · R$ <?= e(number_format((float) $service['price'], 2, ',', '.')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="manual-booking-step">
          <span class="booking-step-number">3</span>
          <div class="flex-grow-1">
            <label class="form-label fw-semibold" for="manual-provider-choice">Profissional</label>
            <select class="form-select" id="manual-provider-choice" disabled required>
              <option value="">Escolha um serviço primeiro</option>
            </select>
            <?php if ($role === 'owner'): ?><div class="form-text">“Primeiro disponível” escolhe automaticamente um profissional livre no horário.</div><?php endif; ?>
          </div>
        </div>

        <div class="manual-booking-step">
          <span class="booking-step-number">4</span>
          <div class="flex-grow-1">
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label fw-semibold" for="manual-date">Data</label>
                <input class="form-control" type="date" name="date" id="manual-date" min="<?= e($today) ?>" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold">Horário escolhido</label>
                <div class="manual-selected-time" id="manual-selected-time">Nenhum horário</div>
              </div>
            </div>

            <div class="mt-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-semibold">Horários disponíveis</span>
                <span class="small text-muted-app" id="manual-slot-count"></span>
              </div>
              <div class="slot-grid public-slot-grid" id="manual-slots"><span class="small text-muted-app">Escolha serviço, profissional e data.</span></div>
            </div>
          </div>
        </div>

        <div class="manual-booking-step border-0 pb-0">
          <span class="booking-step-number"><i class="bi bi-chat-left-text"></i></span>
          <div class="flex-grow-1">
            <label class="form-label fw-semibold" for="manual-notes">Observação <span class="text-muted-app fw-normal">(opcional)</span></label>
            <textarea class="form-control" name="notes" id="manual-notes" rows="3" maxlength="500" placeholder="Informações deste atendimento..."></textarea>
          </div>
        </div>
      </div>
      <div class="card-footer bg-white p-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
        <div class="small text-muted-app" id="manual-summary">Preencha os dados para criar o agendamento.</div>
        <button class="btn btn-dark px-4" type="submit" id="manual-submit" disabled><i class="bi bi-calendar2-check me-2"></i>Criar agendamento</button>
      </div>
    </form>
  </div>

  <div class="col-lg-5">
    <div class="card crm-card">
      <div class="card-body p-4">
        <div class="icon-box icon-box-sm mb-3"><i class="bi bi-shield-check"></i></div>
        <h2 class="h5">Sem conflito de agenda</h2>
        <p class="small text-muted-app mb-3">O horário só aparece se respeitar funcionamento, feriados, jornada do profissional, bloqueios e outros atendimentos.</p>
        <div class="small d-grid gap-2">
          <div><i class="bi bi-check2 me-2"></i>Mesmo motor da reserva pública</div>
          <div><i class="bi bi-check2 me-2"></i>Histórico fica ligado ao cliente</div>
          <div><i class="bi bi-check2 me-2"></i>Conta de login não é obrigatória</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const manualProvidersByService = <?= json_encode($providersByService, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const manualAvailabilityUrl = <?= json_encode(url('/painel/agendamentos/novo/horarios')) ?>;
const manualRole = <?= json_encode($role) ?>;
const serviceSelect = document.getElementById('manual-service');
const providerSelect = document.getElementById('manual-provider-choice');
const employeeInput = document.getElementById('manual-employee-id');
const dateInput = document.getElementById('manual-date');
const timeInput = document.getElementById('manual-time');
const slotsBox = document.getElementById('manual-slots');
const slotCount = document.getElementById('manual-slot-count');
const selectedTimeBox = document.getElementById('manual-selected-time');
const submitButton = document.getElementById('manual-submit');
const summary = document.getElementById('manual-summary');
const customerSelect = document.getElementById('manual-customer');
let chosenProviderName = '';

serviceSelect.addEventListener('change', () => {
  const providers = manualProvidersByService[serviceSelect.value] || [];
  const anyOption = manualRole === 'owner' && providers.length > 1 ? '<option value="0">Primeiro profissional disponível</option>' : '';
  providerSelect.innerHTML = '<option value="">Selecione</option>' + anyOption + providers.map(p => `<option value="${p.id}">${escapeManual(p.name)}</option>`).join('');
  providerSelect.disabled = providers.length === 0;
  employeeInput.value = '';
  chosenProviderName = '';
  resetManualTime();

  if (providers.length === 1) {
    providerSelect.value = String(providers[0].id);
    employeeInput.value = String(providers[0].id);
    chosenProviderName = providers[0].name;
  }
  updateManualSummary();
  if (dateInput.value && providerSelect.value !== '') loadManualSlots();
});

providerSelect.addEventListener('change', () => {
  const option = providerSelect.options[providerSelect.selectedIndex];
  chosenProviderName = providerSelect.value === '0' ? 'Primeiro disponível' : (option?.textContent || '');
  employeeInput.value = providerSelect.value === '0' ? '' : providerSelect.value;
  resetManualTime();
  updateManualSummary();
  loadManualSlots();
});

dateInput.addEventListener('change', () => {
  resetManualTime();
  updateManualSummary();
  loadManualSlots();
});
customerSelect?.addEventListener('change', updateManualSummary);

async function loadManualSlots() {
  if (!serviceSelect.value || providerSelect.value === '' || !dateInput.value) {
    slotsBox.innerHTML = '<span class="small text-muted-app">Escolha serviço, profissional e data.</span>';
    slotCount.textContent = '';
    return;
  }

  slotsBox.innerHTML = '<span class="booking-loading"><span class="spinner-border spinner-border-sm"></span> Buscando horários...</span>';
  slotCount.textContent = '';
  const query = new URLSearchParams({service_id: serviceSelect.value, employee_id: providerSelect.value, date: dateInput.value});
  try {
    const response = await fetch(`${manualAvailabilityUrl}?${query.toString()}`, {headers: {'Accept': 'application/json'}});
    const data = await response.json();
    if (!response.ok || !Array.isArray(data.slots)) throw new Error('availability');
    if (!data.slots.length) {
      slotsBox.innerHTML = '<div class="booking-inline-alert w-100"><i class="bi bi-calendar-x"></i><span>Sem horários livres nesta data.</span></div>';
      return;
    }

    slotCount.textContent = `${data.slots.length} horário${data.slots.length === 1 ? '' : 's'}`;
    slotsBox.innerHTML = data.slots.map(slot => `<button type="button" class="public-slot" data-time="${slot.time}" data-provider-id="${slot.employee_id}" data-provider-name="${escapeManual(slot.employee_name)}">${slot.time}</button>`).join('');
    [...slotsBox.querySelectorAll('.public-slot')].forEach(button => button.addEventListener('click', () => selectManualSlot(button)));
  } catch (_) {
    slotsBox.innerHTML = '<div class="booking-inline-alert booking-inline-alert-danger w-100"><i class="bi bi-exclamation-triangle"></i><span>Não foi possível carregar os horários.</span></div>';
  }
}

function selectManualSlot(button) {
  [...slotsBox.querySelectorAll('.public-slot')].forEach(item => item.classList.toggle('active', item === button));
  timeInput.value = button.dataset.time;
  employeeInput.value = button.dataset.providerId;
  chosenProviderName = button.dataset.providerName;
  selectedTimeBox.textContent = `${button.dataset.time} · ${button.dataset.providerName}`;
  updateManualSummary();
}

function resetManualTime() {
  timeInput.value = '';
  selectedTimeBox.textContent = 'Nenhum horário';
  submitButton.disabled = true;
  if (serviceSelect.value && providerSelect.value !== '' && dateInput.value) {
    slotsBox.innerHTML = '<span class="small text-muted-app">Carregando disponibilidade...</span>';
  }
}

function updateManualSummary() {
  const serviceOption = serviceSelect.options[serviceSelect.selectedIndex];
  const customerOption = customerSelect?.options[customerSelect.selectedIndex];
  if (serviceSelect.value && customerSelect?.value) {
    summary.textContent = `${customerOption.textContent.trim()} · ${serviceOption.dataset.name || serviceOption.textContent.trim()}${timeInput.value ? ` · ${dateInput.value.split('-').reverse().join('/')} às ${timeInput.value}` : ''}`;
  } else {
    summary.textContent = 'Preencha os dados para criar o agendamento.';
  }
  submitButton.disabled = !(customerSelect?.value && serviceSelect.value && employeeInput.value && dateInput.value && timeInput.value);
}

function escapeManual(value) {
  const div = document.createElement('div');
  div.textContent = String(value || '');
  return div.innerHTML;
}
</script>
