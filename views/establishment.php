<?php

use App\Core\Auth;
use App\Core\Csrf;

$timezone = new DateTimeZone((string) ($establishment['timezone'] ?: env('APP_TIMEZONE', 'America/Sao_Paulo')));
$todayDate = new DateTimeImmutable('today', $timezone);
$today = $todayDate->format('Y-m-d');
$quickDates = [];
$weekdays = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
$months = [1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

for ($i = 0; $i < 7; $i++) {
    $dateItem = $todayDate->modify('+' . $i . ' days');
    $quickDates[] = [
        'value' => $dateItem->format('Y-m-d'),
        'weekday' => $i === 0 ? 'Hoje' : $weekdays[(int) $dateItem->format('w')],
        'day' => $dateItem->format('d'),
        'month' => $months[(int) $dateItem->format('n')],
    ];
}

$user = Auth::user();
?>
<section class="public-establishment-header">
  <a href="<?= e(url('/encontre')) ?>" class="small text-decoration-none text-muted-app"><i class="bi bi-arrow-left me-1"></i>Voltar para estabelecimentos</a>
  <div class="row g-4 mt-2 align-items-end">
    <div class="col-lg-8">
      <span class="eyebrow mb-3">Agendamento online</span>
      <h1 class="display-6 fw-bold mb-2"><?= e($establishment['name']) ?></h1>
      <?php if (!empty($establishment['description'])): ?>
        <p class="lead public-establishment-description mb-3"><?= e($establishment['description']) ?></p>
      <?php endif; ?>
      <div class="d-flex flex-wrap gap-3 small text-muted-app">
        <?php if ($establishment['address_line']): ?>
          <span><i class="bi bi-geo-alt me-1"></i><?= e($establishment['address_line']) ?>, <?= e($establishment['city']) ?> - <?= e($establishment['state']) ?></span>
        <?php endif; ?>
        <span><i class="bi bi-clock me-1"></i>Escolha um horário disponível</span>
      </div>
    </div>
  </div>
</section>

<div class="row g-4 align-items-start pb-4">
  <div class="col-lg-7">
    <div class="d-flex justify-content-between align-items-end mb-3">
      <div>
        <div class="small text-muted-app mb-1">1. Escolha o que deseja</div>
        <h2 class="h4 mb-0">Serviços</h2>
      </div>
      <span class="small text-muted-app"><?= count($services) ?> disponível(is)</span>
    </div>

    <div class="d-grid gap-3" id="public-service-list">
      <?php foreach ($services as $service): ?>
        <?php $serviceId = (int) $service['id']; ?>
        <button type="button" class="public-service-card text-start" data-service-id="<?= $serviceId ?>" data-service-name="<?= e($service['name']) ?>" data-service-price="<?= e(number_format((float) $service['price'], 2, ',', '.')) ?>" data-service-duration="<?= (int) $service['duration_minutes'] ?>" aria-pressed="false">
          <span class="public-service-main">
            <span class="public-service-title"><?= e($service['name']) ?></span>
            <?php if (!empty($service['description'])): ?><span class="public-service-description"><?= e($service['description']) ?></span><?php endif; ?>
            <span class="public-service-meta"><i class="bi bi-clock me-1"></i><?= (int) $service['duration_minutes'] ?> min</span>
          </span>
          <span class="public-service-side"><strong>R$ <?= e(number_format((float) $service['price'], 2, ',', '.')) ?></strong><span class="public-service-select">Selecionar <i class="bi bi-arrow-right"></i></span></span>
        </button>
      <?php endforeach; ?>
      <?php if ($services === []): ?><div class="card empty-state"><div class="card-body p-5 text-center text-muted-app">Este estabelecimento ainda não possui serviços disponíveis para agendamento.</div></div><?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card public-booking-card sticky-lg-top" id="booking" style="top: 88px">
      <div class="card-body p-4 p-xl-5">
        <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
          <div><div class="small text-muted-app mb-1">Reserve em poucos passos</div><h2 class="h4 mb-0">Agendar horário</h2></div>
          <span class="booking-lock"><i class="bi bi-shield-check"></i></span>
        </div>

        <form method="post" action="<?= e(url('/estabelecimentos/' . $establishment['slug'] . '/agendar')) ?>" id="booking-form">
          <?= Csrf::field() ?>
          <input type="hidden" name="service_id" id="service">
          <input type="hidden" name="employee_id" id="employee">
          <input type="hidden" name="time" id="selected-time">

          <div class="booking-selected-service mb-4" id="selected-service-summary"><div class="booking-step-number">1</div><div><strong>Escolha um serviço</strong><small>Clique em um serviço ao lado para começar.</small></div></div>

          <div class="booking-section is-disabled" id="provider-section">
            <div class="booking-section-heading"><span class="booking-step-number">2</span><div><strong>Profissional</strong><small>Escolha alguém ou deixe o sistema encontrar o primeiro horário.</small></div></div>
            <div class="provider-public-list mt-3" id="provider-list"><span class="small text-muted-app">Escolha um serviço primeiro.</span></div>
          </div>

          <div class="booking-section is-disabled" id="date-section">
            <div class="booking-section-heading"><span class="booking-step-number">3</span><div><strong>Data e horário</strong><small>Mostramos apenas horários realmente livres.</small></div></div>
            <div class="booking-date-strip mt-3" id="quick-dates">
              <?php foreach ($quickDates as $dateItem): ?>
                <button type="button" class="booking-date-option" data-date="<?= e($dateItem['value']) ?>"><span><?= e($dateItem['weekday']) ?></span><strong><?= e($dateItem['day']) ?></strong><small><?= e($dateItem['month']) ?></small></button>
              <?php endforeach; ?>
            </div>
            <div class="mt-3"><label class="form-label small text-muted-app" for="date">Outra data</label><input class="form-control" type="date" name="date" id="date" min="<?= e($today) ?>" required></div>
            <div class="mt-4">
              <div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label mb-0">Horários disponíveis</label><span class="small text-muted-app" id="slot-count"></span></div>
              <div id="slots" class="slot-grid public-slot-grid"><span class="small text-muted-app">Escolha profissional e data.</span></div>
            </div>
          </div>

          <div class="booking-section booking-notes-section"><label class="form-label">Observação <span class="text-muted-app fw-normal">(opcional)</span></label><textarea class="form-control" rows="2" name="notes" maxlength="500" placeholder="Algo que o estabelecimento precisa saber?"></textarea></div>
          <div class="booking-summary d-none" id="booking-summary"><div><small>Seu agendamento</small><strong id="summary-main"></strong><span id="summary-detail"></span></div><strong id="summary-price"></strong></div>
          <button class="btn btn-dark btn-lg w-100" type="submit" id="booking-submit" disabled><i class="bi bi-calendar2-check me-2"></i>Confirmar agendamento</button>
          <?php if (!$user): ?><div class="small text-muted-app text-center mt-3"><i class="bi bi-person me-1"></i>Você fará login ou criará sua conta para concluir.</div><?php elseif (($user['role'] ?? null) !== 'client'): ?><div class="small text-muted-app text-center mt-3">O agendamento público é destinado a contas de cliente.</div><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
const employeesByService = <?= json_encode($employeesByService, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const availabilityUrl = <?= json_encode(url('/estabelecimentos/' . $establishment['slug'] . '/horarios')) ?>;
const serviceInput = document.getElementById('service');
const employeeInput = document.getElementById('employee');
const timeInput = document.getElementById('selected-time');
const dateInput = document.getElementById('date');
const slots = document.getElementById('slots');
const slotCount = document.getElementById('slot-count');
const providerSection = document.getElementById('provider-section');
const providerList = document.getElementById('provider-list');
const dateSection = document.getElementById('date-section');
const selectedServiceSummary = document.getElementById('selected-service-summary');
const summary = document.getElementById('booking-summary');
const summaryMain = document.getElementById('summary-main');
const summaryDetail = document.getElementById('summary-detail');
const summaryPrice = document.getElementById('summary-price');
const submitButton = document.getElementById('booking-submit');
const serviceCards = [...document.querySelectorAll('.public-service-card')];
const dateButtons = [...document.querySelectorAll('.booking-date-option')];
let selectedService = null;
let selectedProviderChoice = null;
let slotProviderName = '';

serviceCards.forEach(card => card.addEventListener('click', () => selectService(card)));
dateButtons.forEach(button => button.addEventListener('click', () => { dateButtons.forEach(item => item.classList.remove('active')); button.classList.add('active'); dateInput.value = button.dataset.date; resetTime(); loadSlots(); }));
dateInput.addEventListener('change', () => { dateButtons.forEach(item => item.classList.toggle('active', item.dataset.date === dateInput.value)); resetTime(); loadSlots(); });

function selectService(card) {
  serviceCards.forEach(item => { const active = item === card; item.classList.toggle('active', active); item.setAttribute('aria-pressed', active ? 'true' : 'false'); });
  selectedService = {id: card.dataset.serviceId, name: card.dataset.serviceName, price: card.dataset.servicePrice, duration: card.dataset.serviceDuration};
  serviceInput.value = selectedService.id; employeeInput.value = ''; selectedProviderChoice = null; slotProviderName = ''; resetTime();
  selectedServiceSummary.classList.add('has-value');
  selectedServiceSummary.innerHTML = `<div class="booking-step-number"><i class="bi bi-check2"></i></div><div><strong>${escapeHtml(selectedService.name)}</strong><small>${escapeHtml(selectedService.duration)} min · R$ ${escapeHtml(selectedService.price)}</small></div>`;
  renderProviders(); updateSummary();
  if (window.innerWidth < 992) document.getElementById('booking').scrollIntoView({behavior: 'smooth', block: 'start'});
}

function renderProviders() {
  const people = employeesByService[serviceInput.value] || [];
  providerSection.classList.remove('is-disabled');
  if (!people.length) { providerList.innerHTML = '<div class="booking-inline-alert"><i class="bi bi-exclamation-circle"></i><span>Este serviço está temporariamente sem profissional disponível.</span></div>'; dateSection.classList.add('is-disabled'); return; }
  const anyProvider = people.length > 1 ? `<button type="button" class="provider-public-option provider-public-any" data-provider-id="0" data-provider-name="Primeiro disponível"><span class="provider-avatar"><i class="bi bi-lightning-charge"></i></span><span><strong>Primeiro horário disponível</strong><small>O sistema escolhe um profissional livre</small></span><i class="bi bi-check-circle-fill"></i></button>` : '';
  providerList.innerHTML = anyProvider + people.map(person => `<button type="button" class="provider-public-option" data-provider-id="${person.id}" data-provider-name="${escapeHtml(person.name)}"><span class="provider-avatar">${escapeHtml(person.name.charAt(0).toUpperCase())}</span><span><strong>${escapeHtml(person.name)}</strong><small>${person.role === 'owner' ? 'Responsável pelo estabelecimento' : 'Profissional'}</small></span><i class="bi bi-check-circle-fill"></i></button>`).join('');
  [...providerList.querySelectorAll('.provider-public-option')].forEach(button => button.addEventListener('click', () => selectProvider(button)));
  if (people.length === 1) selectProvider(providerList.querySelector('.provider-public-option'));
}

function selectProvider(button) {
  [...providerList.querySelectorAll('.provider-public-option')].forEach(item => item.classList.toggle('active', item === button));
  selectedProviderChoice = {id: button.dataset.providerId, name: button.dataset.providerName, any: button.dataset.providerId === '0'};
  employeeInput.value = selectedProviderChoice.any ? '' : selectedProviderChoice.id;
  slotProviderName = selectedProviderChoice.any ? '' : selectedProviderChoice.name;
  dateSection.classList.remove('is-disabled'); resetTime(); updateSummary(); if (dateInput.value) loadSlots();
}

async function loadSlots() {
  if (!serviceInput.value || !selectedProviderChoice || !dateInput.value) { slots.innerHTML = '<span class="small text-muted-app">Escolha profissional e data.</span>'; slotCount.textContent = ''; return; }
  slots.innerHTML = '<span class="booking-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Buscando horários livres...</span>'; slotCount.textContent = '';
  const query = new URLSearchParams({service_id: serviceInput.value, employee_id: selectedProviderChoice.id, date: dateInput.value});
  try {
    const response = await fetch(`${availabilityUrl}?${query.toString()}`, {headers: {'Accept': 'application/json'}});
    const data = await response.json(); if (!response.ok || !Array.isArray(data.slots)) throw new Error('availability');
    if (!data.slots.length) { slots.innerHTML = '<div class="booking-inline-alert w-100"><i class="bi bi-calendar-x"></i><span>Não há horários livres nessa data. Tente outro dia.</span></div>'; return; }
    slotCount.textContent = `${data.slots.length} horário${data.slots.length === 1 ? '' : 's'}`;
    slots.innerHTML = data.slots.map(slot => `<button type="button" class="public-slot" data-time="${slot.time}" data-provider-id="${slot.employee_id}" data-provider-name="${escapeHtml(slot.employee_name)}">${slot.time}</button>`).join('');
    [...slots.querySelectorAll('.public-slot')].forEach(button => button.addEventListener('click', () => selectTime(button)));
  } catch (_) { slots.innerHTML = '<div class="booking-inline-alert booking-inline-alert-danger w-100"><i class="bi bi-exclamation-triangle"></i><span>Não foi possível carregar os horários. Tente novamente.</span></div>'; }
}

function selectTime(button) { [...slots.querySelectorAll('.public-slot')].forEach(item => item.classList.toggle('active', item === button)); timeInput.value = button.dataset.time; employeeInput.value = button.dataset.providerId; slotProviderName = button.dataset.providerName; updateSummary(); }
function resetTime() { timeInput.value = ''; slotProviderName = selectedProviderChoice && !selectedProviderChoice.any ? selectedProviderChoice.name : ''; employeeInput.value = selectedProviderChoice && !selectedProviderChoice.any ? selectedProviderChoice.id : ''; submitButton.disabled = true; summary.classList.add('d-none'); slots.innerHTML = selectedProviderChoice && dateInput.value ? '<span class="small text-muted-app">Carregando disponibilidade...</span>' : '<span class="small text-muted-app">Escolha profissional e data.</span>'; slotCount.textContent = ''; }
function updateSummary() { if (!selectedService || !selectedProviderChoice || !dateInput.value || !timeInput.value || !employeeInput.value) { summary.classList.add('d-none'); submitButton.disabled = true; return; } const [year, month, day] = dateInput.value.split('-'); summaryMain.textContent = `${day}/${month} às ${timeInput.value}`; summaryDetail.textContent = `${selectedService.name} · ${slotProviderName || selectedProviderChoice.name}`; summaryPrice.textContent = `R$ ${selectedService.price}`; summary.classList.remove('d-none'); submitButton.disabled = false; }
function escapeHtml(value) { const div = document.createElement('div'); div.textContent = String(value ?? ''); return div.innerHTML; }
</script>
