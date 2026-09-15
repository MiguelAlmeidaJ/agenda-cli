document.addEventListener('DOMContentLoaded', () => {
  const bookingForm = document.getElementById('booking-form');
  const slots = document.getElementById('slots');
  const service = document.getElementById('service');
  const employee = document.getElementById('employee');
  const date = document.getElementById('date');
  if (!bookingForm || !slots || !service || !date) return;

  const action = bookingForm.getAttribute('action') || '';
  const waitlistAction = action.replace(/\/agendar$/, '/lista-espera');
  const csrf = bookingForm.querySelector('input[name="_csrf"]');
  let waitlistBox = null;

  function refreshWaitlist() {
    const hasSlots = slots.querySelector('.public-slot');
    const noAvailability = slots.textContent.includes('Não há horários livres');
    if (hasSlots || !noAvailability || !service.value || !date.value) {
      if (waitlistBox) waitlistBox.remove();
      waitlistBox = null;
      return;
    }
    if (waitlistBox) return;

    waitlistBox = document.createElement('div');
    waitlistBox.className = 'mt-3 p-3 border rounded-3 bg-white';
    waitlistBox.innerHTML = `
      <div class="d-flex gap-2 align-items-start mb-2">
        <i class="bi bi-bell"></i>
        <div><strong class="d-block small">Quer ser avisado se surgir uma vaga?</strong><span class="small text-muted">Entre na lista de espera para esta data.</span></div>
      </div>
      <form method="post" action="${waitlistAction}" class="d-grid gap-2">
        ${csrf ? `<input type="hidden" name="_csrf" value="${escapeHtml(csrf.value)}">` : ''}
        <input type="hidden" name="service_id" value="${escapeHtml(service.value)}">
        <input type="hidden" name="preferred_employee_user_id" value="${employee && employee.value ? escapeHtml(employee.value) : '0'}">
        <input type="hidden" name="desired_date" value="${escapeHtml(date.value)}">
        <select class="form-select form-select-sm" name="time_period">
          <option value="any">Qualquer horário</option>
          <option value="morning">Manhã</option>
          <option value="afternoon">Tarde</option>
          <option value="evening">Noite</option>
        </select>
        <button class="btn btn-outline-dark btn-sm" type="submit"><i class="bi bi-list-ul me-1"></i>Entrar na lista de espera</button>
      </form>`;
    slots.insertAdjacentElement('afterend', waitlistBox);
  }

  const observer = new MutationObserver(refreshWaitlist);
  observer.observe(slots, {childList: true, subtree: true, characterData: true});
  service.addEventListener('change', refreshWaitlist);
  date.addEventListener('change', () => setTimeout(refreshWaitlist, 0));

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value || '');
    return div.innerHTML;
  }
});
