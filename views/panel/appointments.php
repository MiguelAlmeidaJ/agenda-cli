<?php
$statusLabels = [
    'pending' => 'Pendente',
    'confirmed' => 'Confirmado',
    'completed' => 'Concluído',
    'cancelled' => 'Cancelado',
    'no_show' => 'Não compareceu',
];
?>
<section class="page-header">
  <div>
    <div class="small text-muted-app mb-1">Agenda</div>
    <h1 class="h3 mb-0">Agendamentos</h1>
  </div>
</section>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th class="ps-4">Data</th>
            <?php if ($role === 'client' || $role === 'admin'): ?><th>Estabelecimento</th><?php endif; ?>
            <th>Serviço</th>
            <th>Profissional</th>
            <?php if ($role !== 'client'): ?><th>Cliente</th><?php endif; ?>
            <th>Status</th>
            <th class="pe-4 text-end">Valor</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($appointments as $appointment): ?>
          <tr>
            <td class="ps-4 text-nowrap">
              <div class="fw-semibold"><?= e(date('d/m/Y', strtotime((string) $appointment['starts_at']))) ?></div>
              <div class="small text-muted-app"><?= e(date('H:i', strtotime((string) $appointment['starts_at']))) ?></div>
            </td>
            <?php if ($role === 'client' || $role === 'admin'): ?><td><?= e($appointment['establishment_name']) ?></td><?php endif; ?>
            <td><?= e($appointment['service_name']) ?></td>
            <td><?= e($appointment['employee_name']) ?></td>
            <?php if ($role !== 'client'): ?><td><?= e($appointment['client_name']) ?></td><?php endif; ?>
            <td><span class="badge badge-soft"><?= e($statusLabels[$appointment['status']] ?? $appointment['status']) ?></span></td>
            <td class="pe-4 text-end text-nowrap">R$ <?= e(number_format((float) $appointment['price'], 2, ',', '.')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($appointments === []): ?>
          <tr><td colspan="7" class="text-center text-muted-app py-5">Nenhum agendamento encontrado.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
