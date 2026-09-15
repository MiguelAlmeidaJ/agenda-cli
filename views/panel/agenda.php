<?php
$statusLabels = [
    'pending' => 'Pendente',
    'confirmed' => 'Confirmado',
    'completed' => 'Concluído',
    'cancelled' => 'Cancelado',
    'no_show' => 'Não compareceu',
];
$previous = $day->modify('-1 day')->format('Y-m-d');
$next = $day->modify('+1 day')->format('Y-m-d');
?>
<section class="page-header agenda-page-header">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <div class="small text-muted-app mb-1">Operação</div>
      <h1 class="h3 mb-1">Agenda do dia</h1>
      <p class="text-muted-app mb-0"><?= e($day->format('d/m/Y')) ?></p>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center">
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/painel/agenda?date=' . $previous)) ?>" aria-label="Dia anterior"><i class="bi bi-chevron-left"></i></a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/painel/agenda')) ?>">Hoje</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/painel/agenda?date=' . $next)) ?>" aria-label="Próximo dia"><i class="bi bi-chevron-right"></i></a>
      <form method="get" action="<?= e(url('/painel/agenda')) ?>" class="d-flex gap-2">
        <input class="form-control form-control-sm" type="date" name="date" value="<?= e($date) ?>">
        <button class="btn btn-dark btn-sm" type="submit">Ir</button>
      </form>
    </div>
  </div>
</section>

<div class="agenda-board">
  <?php foreach ($providers as $provider):
    $items = $appointmentsByProvider[(int) $provider['id']] ?? [];
  ?>
    <section class="agenda-provider-column">
      <div class="agenda-provider-header">
        <div class="d-flex align-items-center gap-2">
          <div class="team-avatar team-avatar-sm"><?= e(strtoupper(substr((string) $provider['name'], 0, 1))) ?></div>
          <div>
            <div class="fw-semibold"><?= e($provider['name']) ?></div>
            <div class="small text-muted-app"><?= count($items) ?> atendimento(s)</div>
          </div>
        </div>
      </div>

      <div class="agenda-provider-body">
        <?php foreach ($items as $appointment): ?>
          <a class="agenda-appointment-card text-decoration-none" href="<?= e(url('/painel/agendamentos/' . $appointment['id'] . '/editar')) ?>">
            <div class="agenda-appointment-time">
              <strong><?= e(date('H:i', strtotime((string) $appointment['starts_at']))) ?></strong>
              <span><?= e(date('H:i', strtotime((string) $appointment['ends_at']))) ?></span>
            </div>
            <div class="agenda-appointment-content">
              <div class="d-flex justify-content-between gap-2 align-items-start">
                <div class="fw-semibold text-dark"><?= e($appointment['client_name']) ?></div>
                <span class="status-pill status-<?= e($appointment['status']) ?>"><?= e($statusLabels[$appointment['status']] ?? $appointment['status']) ?></span>
              </div>
              <div class="small text-muted-app mt-1"><?= e($appointment['service_name']) ?></div>
              <div class="small text-dark mt-2">R$ <?= e(number_format((float) $appointment['price'], 2, ',', '.')) ?></div>
            </div>
          </a>
        <?php endforeach; ?>

        <?php if ($items === []): ?>
          <div class="agenda-empty-column">
            <i class="bi bi-calendar2"></i>
            <span>Sem atendimentos</span>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <?php if ($providers === []): ?>
    <div class="card empty-state w-100">
      <div class="card-body p-5 text-center text-muted-app">Nenhum profissional ativo encontrado.</div>
    </div>
  <?php endif; ?>
</div>
