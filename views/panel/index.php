<?php
$roleLabels = [
    'admin' => 'Administrador',
    'owner' => 'Dono do estabelecimento',
    'employee' => 'Funcionário',
    'client' => 'Cliente',
];

$statusLabels = [
    'pending' => 'Pendente',
    'confirmed' => 'Confirmado',
    'completed' => 'Concluído',
    'cancelled' => 'Cancelado',
    'no_show' => 'Não compareceu',
];

$statusClasses = [
    'pending' => 'status-pending',
    'confirmed' => 'status-confirmed',
    'completed' => 'status-completed',
    'cancelled' => 'status-cancelled',
    'no_show' => 'status-cancelled',
];
?>

<?php if ($role === 'owner'): ?>
  <?php $establishment = $dashboard['establishment'] ?? null; ?>
  <section class="dashboard-header">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
      <div>
        <div class="small text-muted-app mb-1"><?= e($establishment['name'] ?? 'Seu estabelecimento') ?></div>
        <h1 class="h2 mb-2">Visão geral</h1>
        <p class="text-muted-app mb-0">Acompanhe sua agenda e o movimento do negócio em um só lugar.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <?php if ($establishment && !empty($establishment['slug'])): ?>
          <a class="btn btn-outline-secondary" href="<?= e(url('/estabelecimentos/' . $establishment['slug'])) ?>">
            <i class="bi bi-box-arrow-up-right me-2"></i>Ver página pública
          </a>
        <?php endif; ?>
        <a class="btn btn-dark" href="<?= e(url('/painel/agendamentos')) ?>">
          <i class="bi bi-calendar3 me-2"></i>Ver agenda
        </a>
      </div>
    </div>
  </section>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="dashboard-metric h-100">
        <div class="dashboard-metric-icon"><i class="bi bi-calendar2-day"></i></div>
        <div class="small text-muted-app mb-1">Agendamentos hoje</div>
        <div class="dashboard-metric-value"><?= (int) ($metrics['Agendamentos hoje'] ?? 0) ?></div>
        <div class="dashboard-metric-note"><?= (int) ($dashboard['pendingToday'] ?? 0) ?> pendente(s)</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="dashboard-metric h-100">
        <div class="dashboard-metric-icon"><i class="bi bi-calendar-week"></i></div>
        <div class="small text-muted-app mb-1">Próximos 7 dias</div>
        <div class="dashboard-metric-value"><?= (int) ($metrics['Próximos 7 dias'] ?? 0) ?></div>
        <div class="dashboard-metric-note">agendamento(s) previsto(s)</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="dashboard-metric h-100">
        <div class="dashboard-metric-icon"><i class="bi bi-cash-stack"></i></div>
        <div class="small text-muted-app mb-1">Previsto para hoje</div>
        <div class="dashboard-metric-value dashboard-metric-money">R$ <?= e(number_format((float) ($dashboard['todayRevenue'] ?? 0), 2, ',', '.')) ?></div>
        <div class="dashboard-metric-note">agendamentos ativos do dia</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="dashboard-metric h-100">
        <div class="dashboard-metric-icon"><i class="bi bi-people"></i></div>
        <div class="small text-muted-app mb-1">Equipe</div>
        <div class="dashboard-metric-value"><?= (int) ($metrics['Funcionários'] ?? 0) ?></div>
        <div class="dashboard-metric-note"><?= (int) ($metrics['Serviços ativos'] ?? 0) ?> serviço(s) ativo(s)</div>
      </div>
    </div>
  </div>

  <div class="row g-4 align-items-start">
    <div class="col-lg-8">
      <div class="card dashboard-card">
        <div class="card-header dashboard-card-header bg-white d-flex justify-content-between align-items-center gap-3">
          <div>
            <h2 class="h5 mb-1">Próximos atendimentos</h2>
            <div class="small text-muted-app">Sua agenda a partir de agora</div>
          </div>
          <a class="small text-decoration-none fw-semibold" href="<?= e(url('/painel/agendamentos')) ?>">Ver todos</a>
        </div>
        <div class="card-body p-0">
          <?php foreach (($dashboard['nextAppointments'] ?? []) as $appointment): ?>
            <?php $startsAt = strtotime((string) $appointment['starts_at']); ?>
            <div class="dashboard-appointment">
              <div class="dashboard-appointment-date">
                <strong><?= e(date('H:i', $startsAt)) ?></strong>
                <span><?= e(date('d/m', $startsAt)) ?></span>
              </div>
              <div class="dashboard-appointment-main">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                  <span class="fw-semibold"><?= e($appointment['client_name']) ?></span>
                  <span class="status-pill <?= e($statusClasses[$appointment['status']] ?? '') ?>"><?= e($statusLabels[$appointment['status']] ?? $appointment['status']) ?></span>
                </div>
                <div class="small text-muted-app"><?= e($appointment['service_name']) ?> · <?= e($appointment['employee_name']) ?></div>
              </div>
              <div class="dashboard-appointment-price">R$ <?= e(number_format((float) $appointment['price'], 2, ',', '.')) ?></div>
            </div>
          <?php endforeach; ?>

          <?php if (($dashboard['nextAppointments'] ?? []) === []): ?>
            <div class="dashboard-empty">
              <div class="dashboard-empty-icon"><i class="bi bi-calendar2-check"></i></div>
              <h3 class="h6 mb-1">Nenhum atendimento próximo</h3>
              <p class="small text-muted-app mb-0">Os próximos agendamentos aparecerão aqui automaticamente.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card dashboard-card mb-4">
        <div class="card-body p-4">
          <div class="small text-muted-app mb-1">Gerenciamento</div>
          <h2 class="h5 mb-4">Acesso rápido</h2>

          <div class="dashboard-actions">
            <a href="<?= e(url('/painel/agendamentos')) ?>" class="dashboard-action">
              <span class="dashboard-action-icon"><i class="bi bi-calendar3"></i></span>
              <span class="flex-grow-1"><strong>Agendamentos</strong><small>Consulte toda a agenda</small></span>
              <i class="bi bi-chevron-right"></i>
            </a>
            <a href="<?= e(url('/painel/servicos')) ?>" class="dashboard-action">
              <span class="dashboard-action-icon"><i class="bi bi-scissors"></i></span>
              <span class="flex-grow-1"><strong>Serviços</strong><small>Valores, duração e profissionais</small></span>
              <i class="bi bi-chevron-right"></i>
            </a>
            <a href="<?= e(url('/painel/horarios')) ?>" class="dashboard-action">
              <span class="dashboard-action-icon"><i class="bi bi-clock"></i></span>
              <span class="flex-grow-1"><strong>Horários</strong><small>Defina quando atende</small></span>
              <i class="bi bi-chevron-right"></i>
            </a>
          </div>
        </div>
      </div>

      <div class="dashboard-tip">
        <i class="bi bi-lightbulb"></i>
        <div>
          <strong>Mantenha sua agenda atualizada</strong>
          <p class="small mb-0">Serviços e horários corretos evitam reservas em períodos indisponíveis.</p>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <section class="page-header">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
      <div>
        <div class="small text-muted-app mb-1"><?= e($roleLabels[$role] ?? $role) ?></div>
        <h1 class="h3 mb-0">Painel</h1>
      </div>
      <?php if ($role === 'client'): ?>
        <a class="btn btn-dark" href="<?= e(url('/encontre')) ?>"><i class="bi bi-search me-2"></i>Encontrar serviço</a>
      <?php endif; ?>
    </div>
  </section>

  <div class="row g-3 mb-4">
    <?php foreach ($metrics as $label => $value): ?>
      <div class="col-sm-6 col-lg-4">
        <div class="dashboard-metric h-100">
          <div class="small text-muted-app mb-2"><?= e($label) ?></div>
          <div class="dashboard-metric-value"><?= (int) $value ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card dashboard-card">
    <div class="card-body p-4">
      <h2 class="h5 mb-3">Acesso rápido</h2>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-dark" href="<?= e(url('/painel/agendamentos')) ?>"><i class="bi bi-calendar3 me-2"></i>Agendamentos</a>
      </div>
    </div>
  </div>
<?php endif; ?>
