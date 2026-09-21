<?php

use App\Core\Csrf;

$currentDate = date('Y-m-d', strtotime((string) $appointment['starts_at']));
$currentTime = date('H:i', strtotime((string) $appointment['starts_at']));
$maxDate = (new DateTimeImmutable('today'))->modify('+' . (int) $appointment['max_advance_days'] . ' days')->format('Y-m-d');
?>
<section class="page-header">
  <div>
    <div class="small text-muted-app mb-1">Meus agendamentos</div>
    <h1 class="h3 mb-1">Reagendar atendimento</h1>
    <p class="text-muted-app mb-0"><?= e($appointment['service_name']) ?> · <?= e($appointment['establishment_name']) ?></p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= e(url('/painel/agendamentos')) ?>"><i class="bi bi-arrow-left me-2"></i>Voltar</a>
</section>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card"><div class="card-body p-4 p-lg-5">
      <div class="alert alert-light border mb-4">
        Horário atual: <strong><?= e(date('d/m/Y H:i', strtotime((string) $appointment['starts_at']))) ?></strong>
        com <?= e($appointment['employee_name']) ?>.
      </div>
      <form method="post" action="<?= e(url('/painel/agendamentos/' . $appointment['id'] . '/reagendar')) ?>" id="reschedule-form">
        <?= Csrf::field() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Profissional</label>
            <select class="form-select" name="employee_id" id="employee-id" required>
              <?php foreach ($providers as $provider): ?>
                <option value="<?= (int) $provider['id'] ?>" <?= (int) $provider['id'] === (int) $appointment['employee_user_id'] ? 'selected' : '' ?>><?= e($provider['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Data</label>
            <input class="form-control" type="date" name="date" id="date" min="<?= e(date('Y-m-d')) ?>" max="<?= e($maxDate) ?>" value="<?= e($currentDate) ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Horário disponível</label>
            <select class="form-select" name="time" id="time" data-current="<?= e($currentTime) ?>" required>
              <option value="">Carregando horários...</option>
            </select>
            <div class="form-text" id="slot-help">A disponibilidade é recalculada antes da confirmação.</div>
          </div>
        </div>
        <div class="d-flex justify-content-end mt-4">
          <button class="btn btn-dark" type="submit"><i class="bi bi-check2 me-2"></i>Confirmar novo horário</button>
        </div>
      </form>
    </div></div>
  </div>
</div>

<script>
(() => {
  const employee = document.getElementById('employee-id');
  const date = document.getElementById('date');
  const time = document.getElementById('time');
  const help = document.getElementById('slot-help');
  const endpoint = <?= json_encode(url('/painel/agendamentos/' . $appointment['id'] . '/reagendar/horarios'), JSON_UNESCAPED_SLASHES) ?>;

  async function loadSlots() {
    const current = time.dataset.current || '';
    time.innerHTML = '<option value="">Carregando horários...</option>';
    time.disabled = true;
    try {
      const query = new URLSearchParams({employee_id: employee.value, date: date.value});
      const response = await fetch(endpoint + '?' + query.toString(), {headers: {'Accept': 'application/json'}});
      const payload = await response.json();
      const slots = Array.isArray(payload.slots) ? payload.slots : [];
      time.innerHTML = '<option value="">Selecione</option>';
      for (const slot of slots) {
        const option = document.createElement('option');
        option.value = slot;
        option.textContent = slot;
        if (slot === current) option.selected = true;
        time.appendChild(option);
      }
      help.textContent = slots.length ? 'Escolha um dos horários disponíveis.' : 'Não há horários disponíveis nessa data.';
    } catch (error) {
      time.innerHTML = '<option value="">Não foi possível carregar</option>';
      help.textContent = 'Atualize a página e tente novamente.';
    } finally {
      time.disabled = false;
      time.dataset.current = '';
    }
  }

  employee.addEventListener('change', loadSlots);
  date.addEventListener('change', loadSlots);
  loadSlots();
})();
</script>
