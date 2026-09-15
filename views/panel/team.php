<?php use App\Core\Csrf; ?>
<section class="page-header team-page-header">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <div class="small text-muted-app mb-1">Operação</div>
      <h1 class="h3 mb-1">Equipe</h1>
      <p class="text-muted-app mb-0">Gerencie quem atende, seus horários e disponibilidade.</p>
    </div>
  </div>
</section>

<div class="row g-4 align-items-start">
  <div class="col-lg-8">
    <div class="d-grid gap-3">
      <?php if ($owner): ?>
        <div class="card team-member-card">
          <div class="card-body p-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
              <div class="team-avatar"><?= e(strtoupper(substr((string) $owner['name'], 0, 1))) ?></div>
              <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                  <h2 class="h6 mb-0"><?= e($owner['name']) ?></h2>
                  <span class="badge badge-soft">Dono</span>
                </div>
                <div class="small text-muted-app"><?= e($owner['email']) ?></div>
                <div class="small text-muted-app mt-1"><?= (int) $owner['service_count'] ?> serviço(s) vinculado(s)</div>
              </div>
            </div>
            <a class="btn btn-outline-dark btn-sm" href="<?= e(url('/painel/equipe/' . $owner['id'] . '/horarios')) ?>">
              <i class="bi bi-clock me-2"></i>Horários individuais
            </a>
          </div>
        </div>
      <?php endif; ?>

      <?php foreach ($employees as $employee): ?>
        <div class="card team-member-card <?= (int) $employee['active'] === 1 ? '' : 'team-member-inactive' ?>">
          <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
              <div class="d-flex align-items-center gap-3">
                <div class="team-avatar"><?= e(strtoupper(substr((string) $employee['name'], 0, 1))) ?></div>
                <div>
                  <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <h2 class="h6 mb-0"><?= e($employee['name']) ?></h2>
                    <span class="status-pill <?= (int) $employee['active'] === 1 ? 'status-confirmed' : 'status-cancelled' ?>">
                      <?= (int) $employee['active'] === 1 ? 'Ativo' : 'Inativo' ?>
                    </span>
                  </div>
                  <div class="small text-muted-app"><?= e($employee['email']) ?></div>
                  <div class="small text-muted-app mt-1"><?= (int) $employee['service_count'] ?> serviço(s) vinculado(s)</div>
                </div>
              </div>

              <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-dark btn-sm" href="<?= e(url('/painel/equipe/' . $employee['id'] . '/horarios')) ?>">
                  <i class="bi bi-clock me-2"></i>Horários
                </a>
                <form method="post" action="<?= e(url('/painel/equipe/' . $employee['id'] . '/status')) ?>">
                  <?= Csrf::field() ?>
                  <button class="btn btn-outline-secondary btn-sm" type="submit">
                    <?= (int) $employee['active'] === 1 ? 'Desativar' : 'Reativar' ?>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if ($employees === []): ?>
        <div class="card empty-state">
          <div class="card-body p-5 text-center">
            <i class="bi bi-people fs-3 text-muted-app"></i>
            <h2 class="h6 mt-3">Sua equipe ainda está só com você</h2>
            <p class="small text-muted-app mb-0">Adicione profissionais para distribuir serviços e agendas.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card sticky-lg-top" style="top: 88px">
      <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2 mb-3">
          <span class="icon-box icon-box-sm"><i class="bi bi-person-plus"></i></span>
          <h2 class="h5 mb-0">Adicionar profissional</h2>
        </div>
        <p class="small text-muted-app">Se o e-mail já pertencer a um funcionário do Agenda CLI, ele será apenas vinculado ao estabelecimento.</p>

        <form method="post" action="<?= e(url('/painel/equipe')) ?>">
          <?= Csrf::field() ?>
          <div class="mb-3">
            <label class="form-label">Nome</label>
            <input class="form-control" name="name" maxlength="120" required>
          </div>
          <div class="mb-3">
            <label class="form-label">E-mail</label>
            <input class="form-control" type="email" name="email" maxlength="190" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Telefone <span class="text-muted-app fw-normal">(opcional)</span></label>
            <input class="form-control" name="phone" maxlength="30">
          </div>
          <div class="mb-4">
            <label class="form-label">Senha inicial</label>
            <input class="form-control" type="password" name="password" minlength="8" autocomplete="new-password">
            <div class="form-text">Obrigatória apenas para uma conta nova.</div>
          </div>
          <button class="btn btn-dark w-100" type="submit">Adicionar à equipe</button>
        </form>
      </div>
    </div>
  </div>
</div>
