<?php
use App\Core\Csrf;

$statusLabels = [
    'pending' => 'Pendente',
    'confirmed' => 'Confirmado',
    'completed' => 'Concluído',
    'cancelled' => 'Cancelado',
    'no_show' => 'Não compareceu',
];
?>
<section class="page-header">
  <a class="small text-decoration-none text-muted-app" href="<?= e(url('/painel/clientes')) ?>"><i class="bi bi-arrow-left me-1"></i>Voltar para clientes</a>
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-3">
    <div class="d-flex align-items-center gap-3">
      <span class="crm-avatar crm-avatar-lg"><?= e(strtoupper(substr((string) $customer['name'], 0, 1))) ?></span>
      <div>
        <div class="d-flex align-items-center flex-wrap gap-2">
          <h1 class="h3 mb-0"><?= e($customer['name']) ?></h1>
          <?php if (!empty($customer['user_id'])): ?><span class="badge badge-soft"><i class="bi bi-person-check me-1"></i>Conta vinculada</span><?php endif; ?>
        </div>
        <div class="small text-muted-app mt-1">
          <?= e($customer['phone'] ?: 'Sem telefone') ?>
          <?php if (!empty($customer['email'])): ?> · <?= e($customer['email']) ?><?php endif; ?>
        </div>
      </div>
    </div>
    <a class="btn btn-dark" href="<?= e(url('/painel/agendamentos/novo?customer_id=' . $customer['id'])) ?>"><i class="bi bi-calendar-plus me-2"></i>Agendar atendimento</a>
  </div>
</section>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="dashboard-metric h-100"><div class="small text-muted-app mb-1">Atendimentos</div><div class="dashboard-metric-value"><?= (int) ($stats['completed'] ?? 0) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="dashboard-metric h-100"><div class="small text-muted-app mb-1">Total atendido</div><div class="dashboard-metric-value dashboard-metric-money">R$ <?= e(number_format((float) ($stats['revenue'] ?? 0), 2, ',', '.')) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="dashboard-metric h-100"><div class="small text-muted-app mb-1">Última visita</div><div class="crm-metric-text"><?= !empty($stats['last_visit']) ? e(date('d/m/Y', strtotime((string) $stats['last_visit']))) : '—' ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="dashboard-metric h-100"><div class="small text-muted-app mb-1">Próximo horário</div><div class="crm-metric-text"><?= !empty($stats['next_appointment']) ? e(date('d/m H:i', strtotime((string) $stats['next_appointment']))) : '—' ?></div></div></div>
</div>

<div class="row g-4 align-items-start">
  <div class="col-lg-8">
    <div class="card crm-card">
      <div class="card-header bg-white p-4">
        <h2 class="h5 mb-1">Histórico de atendimentos</h2>
        <div class="small text-muted-app">Agendamentos mais recentes deste cliente.</div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th class="ps-4">Data</th><th>Serviço</th><th>Profissional</th><th>Status</th><th class="pe-4 text-end">Valor</th></tr></thead>
            <tbody>
            <?php foreach ($appointments as $appointment): ?>
              <tr>
                <td class="ps-4 text-nowrap"><a class="text-dark text-decoration-none fw-semibold" href="<?= e(url('/painel/agendamentos/' . $appointment['id'] . '/editar')) ?>"><?= e(date('d/m/Y H:i', strtotime((string) $appointment['starts_at']))) ?></a></td>
                <td><?= e($appointment['service_name']) ?></td>
                <td><?= e($appointment['employee_name']) ?></td>
                <td><span class="status-pill status-<?= e($appointment['status']) ?>"><?= e($statusLabels[$appointment['status']] ?? $appointment['status']) ?></span></td>
                <td class="pe-4 text-end text-nowrap">R$ <?= e(number_format((float) $appointment['price'], 2, ',', '.')) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if ($appointments === []): ?><tr><td colspan="5" class="py-5 text-center text-muted-app">Ainda não há atendimentos para este cliente.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card crm-card">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Dados do cliente</h2>
        <form method="post" action="<?= e(url('/painel/clientes/' . $customer['id'])) ?>">
          <?= Csrf::field() ?>
          <div class="mb-3"><label class="form-label">Nome</label><input class="form-control" name="name" maxlength="120" required value="<?= e($customer['name']) ?>"></div>
          <div class="mb-3"><label class="form-label">Telefone</label><input class="form-control" name="phone" maxlength="30" value="<?= e($customer['phone'] ?? '') ?>"></div>
          <div class="mb-3"><label class="form-label">E-mail</label><input class="form-control" type="email" name="email" maxlength="190" value="<?= e($customer['email'] ?? '') ?>"></div>
          <div class="mb-4"><label class="form-label">Observação interna</label><textarea class="form-control" name="notes" rows="5" maxlength="2000"><?= e($customer['notes'] ?? '') ?></textarea></div>
          <button class="btn btn-dark w-100">Salvar alterações</button>
        </form>
      </div>
    </div>
  </div>
</div>
