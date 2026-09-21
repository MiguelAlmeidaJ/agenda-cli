<?php

use App\Core\Csrf;

$statusLabels = [
    'pending' => 'Pendente',
    'confirmed' => 'Confirmado',
    'completed' => 'Concluído',
    'cancelled' => 'Cancelado',
    'no_show' => 'Não compareceu',
];
$canManage = in_array($role, ['owner', 'employee'], true);
$showActions = $canManage || $role === 'client';
?>
<section class="page-header">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div><div class="small text-muted-app mb-1">Agenda</div><h1 class="h3 mb-0">Agendamentos</h1></div>
    <?php if ($canManage): ?>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/painel/agenda')) ?>"><i class="bi bi-calendar3 me-2"></i>Agenda do dia</a>
        <a class="btn btn-dark btn-sm" href="<?= e(url('/painel/agendamentos/novo')) ?>"><i class="bi bi-calendar-plus me-2"></i>Novo agendamento</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
  <table class="table align-middle mb-0 appointments-table">
    <thead><tr>
      <th class="ps-4">Data</th>
      <?php if ($role === 'client' || $role === 'admin'): ?><th>Estabelecimento</th><?php endif; ?>
      <th>Serviço</th><th>Profissional</th>
      <?php if ($role !== 'client'): ?><th>Cliente</th><?php endif; ?>
      <th>Status</th><th class="text-end">Valor</th>
      <?php if ($showActions): ?><th class="pe-4 text-end">Ações</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($appointments as $appointment): ?>
      <tr>
        <td class="ps-4 text-nowrap"><div class="fw-semibold"><?= e(date('d/m/Y', strtotime((string) $appointment['starts_at']))) ?></div><div class="small text-muted-app"><?= e(date('H:i', strtotime((string) $appointment['starts_at']))) ?>–<?= e(date('H:i', strtotime((string) $appointment['ends_at']))) ?></div></td>
        <?php if ($role === 'client' || $role === 'admin'): ?><td><?= e($appointment['establishment_name']) ?></td><?php endif; ?>
        <td><?= e($appointment['service_name']) ?></td><td><?= e($appointment['employee_name']) ?></td>
        <?php if ($role !== 'client'): ?><td><?= e($appointment['client_name']) ?></td><?php endif; ?>
        <td><span class="status-pill status-<?= e($appointment['status']) ?>"><?= e($statusLabels[$appointment['status']] ?? $appointment['status']) ?></span></td>
        <td class="text-end text-nowrap">R$ <?= e(number_format((float) $appointment['price'], 2, ',', '.')) ?></td>
        <?php if ($canManage): ?>
          <td class="pe-4 text-end"><div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Gerenciar</button>
            <div class="dropdown-menu dropdown-menu-end appointment-actions-menu p-2">
              <?php if (in_array($appointment['status'], ['pending', 'confirmed'], true)): ?><a class="dropdown-item rounded" href="<?= e(url('/painel/agendamentos/' . $appointment['id'] . '/editar')) ?>"><i class="bi bi-calendar-event me-2"></i>Reagendar</a><?php endif; ?>
              <?php if ($appointment['status'] === 'pending'): ?>
                <form method="post" action="<?= e(url('/painel/agendamentos/' . $appointment['id'] . '/status')) ?>"><?= Csrf::field() ?><input type="hidden" name="status" value="confirmed"><button class="dropdown-item rounded" type="submit"><i class="bi bi-check-circle me-2"></i>Confirmar</button></form>
              <?php endif; ?>
              <?php if ($appointment['status'] === 'confirmed'): ?>
                <form method="post" action="<?= e(url('/painel/agendamentos/' . $appointment['id'] . '/status')) ?>"><?= Csrf::field() ?><input type="hidden" name="status" value="completed"><button class="dropdown-item rounded" type="submit"><i class="bi bi-check2-all me-2"></i>Concluir</button></form>
                <form method="post" action="<?= e(url('/painel/agendamentos/' . $appointment['id'] . '/status')) ?>"><?= Csrf::field() ?><input type="hidden" name="status" value="no_show"><button class="dropdown-item rounded" type="submit"><i class="bi bi-person-x me-2"></i>Não compareceu</button></form>
              <?php endif; ?>
              <?php if (in_array($appointment['status'], ['pending', 'confirmed'], true)): ?>
                <div class="dropdown-divider"></div><form method="post" action="<?= e(url('/painel/agendamentos/' . $appointment['id'] . '/status')) ?>" onsubmit="return confirm('Cancelar este agendamento?')"><?= Csrf::field() ?><input type="hidden" name="status" value="cancelled"><button class="dropdown-item rounded text-danger" type="submit"><i class="bi bi-x-circle me-2"></i>Cancelar</button></form>
              <?php endif; ?>
              <?php if (!in_array($appointment['status'], ['pending', 'confirmed'], true)): ?><a class="dropdown-item rounded" href="<?= e(url('/painel/agendamentos/' . $appointment['id'] . '/editar')) ?>"><i class="bi bi-clock-history me-2"></i>Ver histórico</a><?php endif; ?>
            </div>
          </div></td>
        <?php elseif ($role === 'client'): ?>
          <td class="pe-4 text-end">
            <?php if (in_array($appointment['status'], ['pending', 'confirmed'], true) && !empty($appointment['is_future'])): ?>
              <div class="d-flex justify-content-end gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/painel/agendamentos/' . $appointment['id'] . '/reagendar')) ?>"><i class="bi bi-calendar-event me-1"></i>Reagendar</a>
                <form method="post" action="<?= e(url('/painel/agendamentos/' . $appointment['id'] . '/cancelar')) ?>" onsubmit="return confirm('Deseja cancelar este agendamento?')">
                  <?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger" type="submit">Cancelar</button>
                </form>
              </div>
            <?php else: ?><span class="small text-muted-app">—</span><?php endif; ?>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if ($appointments === []): ?><tr><td colspan="<?= $showActions ? '8' : '7' ?>" class="text-center text-muted-app py-5">Nenhum agendamento encontrado.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div></div></div>
