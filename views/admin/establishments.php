<section class="page-header">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
    <div>
      <div class="small text-muted-app mb-1">Administração</div>
      <h1 class="h3 mb-1">Estabelecimentos</h1>
      <p class="text-muted-app mb-0">Crie negócios, acompanhe seus responsáveis e acesse a configuração de cada unidade.</p>
    </div>
    <a class="btn btn-dark" href="<?= e(url('/admin/estabelecimentos/novo')) ?>"><i class="bi bi-plus-lg me-2"></i>Novo estabelecimento</a>
  </div>
</section>

<div class="card dashboard-card overflow-hidden">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Estabelecimento</th>
          <th>Dono</th>
          <th>Operação</th>
          <th>Status</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($establishments as $establishment): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($establishment['name']) ?></div>
              <div class="small text-muted-app">
                <?php if (!empty($establishment['city'])): ?>
                  <i class="bi bi-geo-alt me-1"></i><?= e($establishment['city']) ?><?= !empty($establishment['state']) ? ' - ' . e($establishment['state']) : '' ?>
                <?php else: ?>
                  Localização não informada
                <?php endif; ?>
              </div>
            </td>
            <td>
              <div><?= e($establishment['owner_name']) ?></div>
              <div class="small text-muted-app"><?= e($establishment['owner_email']) ?></div>
            </td>
            <td>
              <div class="small"><?= (int) $establishment['service_count'] ?> serviço(s)</div>
              <div class="small text-muted-app"><?= (int) $establishment['employee_count'] ?> funcionário(s)</div>
            </td>
            <td>
              <span class="status-pill <?= (int) $establishment['active'] === 1 ? 'status-confirmed' : 'status-cancelled' ?>">
                <?= (int) $establishment['active'] === 1 ? 'Ativo' : 'Inativo' ?>
              </span>
            </td>
            <td class="text-end">
              <div class="d-inline-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" href="<?= e(url('/estabelecimentos/' . $establishment['slug'])) ?>" title="Abrir página pública"><i class="bi bi-box-arrow-up-right"></i></a>
                <a class="btn btn-outline-dark btn-sm" href="<?= e(url('/admin/estabelecimentos/' . $establishment['id'] . '/editar')) ?>"><i class="bi bi-pencil-square me-1"></i>Editar</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($establishments === []): ?>
          <tr><td colspan="5" class="text-center py-5 text-muted-app">Nenhum estabelecimento cadastrado.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
