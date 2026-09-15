<?php
use App\Core\Csrf;
?>
<section class="page-header">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <div class="small text-muted-app mb-1">Relacionamento</div>
      <h1 class="h3 mb-1">Clientes</h1>
      <p class="text-muted-app mb-0">Contatos, histórico de atendimentos e próximos horários.</p>
    </div>
    <a class="btn btn-dark" href="<?= e(url('/painel/agendamentos/novo')) ?>"><i class="bi bi-calendar-plus me-2"></i>Novo agendamento</a>
  </div>
</section>

<div class="row g-4 align-items-start">
  <div class="col-lg-8">
    <div class="card crm-card">
      <div class="card-header bg-white p-3 p-md-4">
        <form method="get" action="<?= e(url('/painel/clientes')) ?>" class="d-flex gap-2">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input class="form-control" name="q" value="<?= e($query) ?>" placeholder="Buscar por nome, telefone ou e-mail">
          </div>
          <button class="btn btn-outline-secondary">Buscar</button>
          <?php if ($query !== ''): ?><a class="btn btn-light" href="<?= e(url('/painel/clientes')) ?>">Limpar</a><?php endif; ?>
        </form>
      </div>
      <div class="card-body p-0">
        <?php foreach ($customers as $customer): ?>
          <a class="crm-customer-row" href="<?= e(url('/painel/clientes/' . $customer['id'])) ?>">
            <span class="crm-avatar"><?= e(strtoupper(substr((string) $customer['name'], 0, 1))) ?></span>
            <span class="crm-customer-main">
              <strong><?= e($customer['name']) ?></strong>
              <small>
                <?php if (!empty($customer['phone'])): ?><?= e($customer['phone']) ?><?php endif; ?>
                <?php if (!empty($customer['phone']) && !empty($customer['email'])): ?> · <?php endif; ?>
                <?php if (!empty($customer['email'])): ?><?= e($customer['email']) ?><?php endif; ?>
              </small>
            </span>
            <span class="crm-customer-stat d-none d-md-block">
              <strong><?= (int) $customer['completed_count'] ?></strong>
              <small>atendimento(s)</small>
            </span>
            <span class="crm-customer-next d-none d-sm-block">
              <?php if (!empty($customer['next_appointment'])): ?>
                <strong><?= e(date('d/m H:i', strtotime((string) $customer['next_appointment']))) ?></strong>
                <small>próximo horário</small>
              <?php elseif (!empty($customer['last_visit'])): ?>
                <strong><?= e(date('d/m/Y', strtotime((string) $customer['last_visit']))) ?></strong>
                <small>última visita</small>
              <?php else: ?>
                <strong>—</strong><small>sem histórico</small>
              <?php endif; ?>
            </span>
            <i class="bi bi-chevron-right text-muted-app"></i>
          </a>
        <?php endforeach; ?>

        <?php if ($customers === []): ?>
          <div class="p-5 text-center">
            <div class="dashboard-empty-icon"><i class="bi bi-person-lines-fill"></i></div>
            <h2 class="h6 mb-1"><?= $query !== '' ? 'Nenhum cliente encontrado' : 'Sua base de clientes começa aqui' ?></h2>
            <p class="small text-muted-app mb-0"><?= $query !== '' ? 'Tente buscar por outro nome ou contato.' : 'Cadastre o primeiro cliente no formulário ao lado.' ?></p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card crm-card sticky-lg-top" style="top: 88px">
      <div class="card-body p-4">
        <div class="small text-muted-app mb-1">Cadastro rápido</div>
        <h2 class="h5 mb-3">Novo cliente</h2>
        <form method="post" action="<?= e(url('/painel/clientes')) ?>">
          <?= Csrf::field() ?>
          <div class="mb-3">
            <label class="form-label">Nome</label>
            <input class="form-control" name="name" maxlength="120" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Telefone</label>
            <input class="form-control" name="phone" maxlength="30" inputmode="tel" placeholder="(32) 99999-9999">
          </div>
          <div class="mb-3">
            <label class="form-label">E-mail</label>
            <input class="form-control" type="email" name="email" maxlength="190" placeholder="cliente@email.com">
          </div>
          <div class="mb-4">
            <label class="form-label">Observação interna <span class="text-muted-app fw-normal">(opcional)</span></label>
            <textarea class="form-control" name="notes" rows="3" maxlength="2000" placeholder="Preferências, informações úteis para o atendimento..."></textarea>
          </div>
          <div class="small text-muted-app mb-3"><i class="bi bi-info-circle me-1"></i>Telefone ou e-mail: pelo menos um deve ser informado.</div>
          <button class="btn btn-dark w-100" type="submit">Cadastrar cliente</button>
        </form>
      </div>
    </div>
  </div>
</div>
