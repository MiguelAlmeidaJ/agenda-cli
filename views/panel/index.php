<?php
$roleLabels = [
    'admin' => 'Administrador',
    'owner' => 'Dono do estabelecimento',
    'employee' => 'Funcionário',
    'client' => 'Cliente',
];
?>
<section class="page-header">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <div class="small text-muted-app mb-1"><?= e($roleLabels[$role] ?? $role) ?></div>
      <h1 class="h3 mb-0">Painel</h1>
    </div>
    <?php if ($role === 'client'): ?>
      <a class="btn btn-dark" href="<?= e(url('/')) ?>"><i class="bi bi-search me-2"></i>Encontrar serviço</a>
    <?php endif; ?>
  </div>
</section>

<div class="row g-3 mb-4">
  <?php foreach ($metrics as $label => $value): ?>
    <div class="col-sm-6 col-lg-4">
      <div class="card h-100"><div class="card-body p-4">
        <div class="small text-muted-app mb-2"><?= e($label) ?></div>
        <div class="metric-value"><?= (int) $value ?></div>
      </div></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-body p-4">
    <h2 class="h5 mb-3">Acesso rápido</h2>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-dark" href="<?= e(url('/painel/agendamentos')) ?>"><i class="bi bi-calendar3 me-2"></i>Agendamentos</a>
      <?php if ($role === 'owner'): ?>
        <a class="btn btn-outline-dark" href="<?= e(url('/painel/servicos')) ?>"><i class="bi bi-scissors me-2"></i>Serviços</a>
        <a class="btn btn-outline-dark" href="<?= e(url('/painel/horarios')) ?>"><i class="bi bi-clock me-2"></i>Horários</a>
      <?php endif; ?>
    </div>
  </div>
</div>
