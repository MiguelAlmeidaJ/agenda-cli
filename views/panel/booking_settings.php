<?php use App\Core\Csrf; ?>
<section class="page-header">
  <div>
    <div class="small text-muted-app mb-1">Configuração</div>
    <h1 class="h3 mb-1">Regras de agendamento</h1>
    <p class="text-muted-app mb-0">Defina como e quando seus clientes podem reservar ou cancelar horários.</p>
  </div>
</section>

<div class="row g-4 align-items-start">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body p-4 p-lg-5">
        <form method="post" action="<?= e(url('/painel/configuracoes/agendamento')) ?>">
          <?= Csrf::field() ?>

          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Antecedência mínima</label>
              <div class="input-group"><input class="form-control" type="number" min="0" max="10080" step="15" name="min_notice_minutes" value="<?= (int) $settings['min_notice_minutes'] ?>"><span class="input-group-text">min</span></div>
              <div class="form-text">Ex.: 120 impede reservas com menos de 2 horas.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Até quantos dias no futuro</label>
              <div class="input-group"><input class="form-control" type="number" min="1" max="365" name="max_advance_days" value="<?= (int) $settings['max_advance_days'] ?>"><span class="input-group-text">dias</span></div>
              <div class="form-text">Limita a janela de reservas futuras.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Intervalo entre atendimentos</label>
              <div class="input-group"><input class="form-control" type="number" min="0" max="180" step="5" name="buffer_minutes" value="<?= (int) $settings['buffer_minutes'] ?>"><span class="input-group-text">min</span></div>
              <div class="form-text">Reserva um tempo antes/depois de cada atendimento ocupado.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Prazo mínimo para cancelamento</label>
              <div class="input-group"><input class="form-control" type="number" min="0" max="10080" step="30" name="cancellation_notice_minutes" value="<?= (int) $settings['cancellation_notice_minutes'] ?>"><span class="input-group-text">min</span></div>
              <div class="form-text">Ex.: 720 permite cancelamento até 12 horas antes.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Prazo mínimo para reagendamento</label>
              <div class="input-group"><input class="form-control" type="number" min="0" max="10080" step="30" name="reschedule_notice_minutes" value="<?= (int) ($settings['reschedule_notice_minutes'] ?? 0) ?>"><span class="input-group-text">min</span></div>
              <div class="form-text">Ex.: 720 permite reagendar online até 12 horas antes.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Validade da oferta da lista de espera</label>
              <div class="input-group"><input class="form-control" type="number" min="5" max="1440" step="5" name="waitlist_offer_minutes" value="<?= (int) ($settings['waitlist_offer_minutes'] ?? 30) ?>"><span class="input-group-text">min</span></div>
              <div class="form-text">Após esse prazo a vaga é liberada para uma nova tentativa.</div>
            </div>
          </div>

          <hr class="my-4">
          <div class="form-check form-switch mb-4">
            <input class="form-check-input" type="checkbox" role="switch" id="allow-waitlist" name="allow_waitlist" <?= (int) $settings['allow_waitlist'] === 1 ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="allow-waitlist">Permitir lista de espera quando não houver horário</label>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold">Política de cancelamento</label>
            <textarea class="form-control" rows="3" maxlength="255" name="cancellation_policy" placeholder="Ex.: Cancelamentos com menos de 12 horas podem exigir novo sinal."><?= e((string) ($settings['cancellation_policy'] ?? '')) ?></textarea>
            <div class="form-text">Esse texto poderá ser exibido ao cliente durante a reserva.</div>
          </div>

          <div class="d-flex justify-content-end"><button class="btn btn-dark px-4" type="submit">Salvar regras</button></div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card"><div class="card-body p-4">
      <h2 class="h6 mb-3">Como as regras se combinam</h2>
      <div class="small text-muted-app d-grid gap-3">
        <div><i class="bi bi-clock-history me-2"></i>A antecedência mínima vale para site e agendamento manual.</div>
        <div><i class="bi bi-hourglass-split me-2"></i>O intervalo é aplicado à agenda do profissional, mesmo entre serviços diferentes.</div>
        <div><i class="bi bi-calendar-range me-2"></i>Feriados, exceções e jornada individual continuam tendo prioridade.</div>
        <div><i class="bi bi-list-ul me-2"></i>A lista de espera não ocupa a agenda; ela apenas registra interesse.</div>
      </div>
    </div></div>
  </div>
</div>
