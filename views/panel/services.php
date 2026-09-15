<?php

use App\Core\Csrf;

$providerById = [];
foreach ($providers as $provider) {
    $providerById[(int) $provider['id']] = $provider;
}
?>
<section class="page-header">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <div class="small text-muted-app mb-1">Gestão do catálogo</div>
      <h1 class="h3 mb-1">Serviços</h1>
      <p class="text-muted-app mb-0">Defina o que você oferece e quem pode realizar cada atendimento.</p>
    </div>
    <span class="badge badge-soft px-3 py-2"><?= count($services) ?> serviço(s)</span>
  </div>
</section>

<div class="row g-4 align-items-start">
  <div class="col-lg-7">
    <div class="d-grid gap-3">
      <?php foreach ($services as $service): ?>
        <?php
          $serviceId = (int) $service['id'];
          $linkedIds = array_map('intval', $assignments[$serviceId] ?? []);
          $linkedProviders = array_values(array_filter($providers, fn (array $provider): bool => in_array((int) $provider['id'], $linkedIds, true)));
        ?>
        <div class="card service-management-card">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div class="flex-grow-1">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                  <h2 class="h5 mb-0"><?= e($service['name']) ?></h2>
                  <span class="badge <?= (int) $service['active'] === 1 ? 'badge-soft' : 'text-bg-light border' ?>">
                    <?= (int) $service['active'] === 1 ? 'Ativo' : 'Inativo' ?>
                  </span>
                </div>
                <?php if (!empty($service['description'])): ?>
                  <p class="small text-muted-app mb-3"><?= e($service['description']) ?></p>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-3 small mb-3">
                  <span><i class="bi bi-clock me-1 text-muted-app"></i><?= (int) $service['duration_minutes'] ?> min</span>
                  <span class="fw-semibold">R$ <?= e(number_format((float) $service['price'], 2, ',', '.')) ?></span>
                </div>

                <div>
                  <div class="small text-muted-app mb-2">Quem realiza</div>
                  <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($linkedProviders as $provider): ?>
                      <span class="provider-chip">
                        <span class="provider-avatar"><?= e(strtoupper(substr((string) $provider['name'], 0, 1))) ?></span>
                        <?= e($provider['name']) ?>
                        <?php if (($provider['provider_role'] ?? '') === 'owner'): ?><span class="text-muted-app">· dono</span><?php endif; ?>
                      </span>
                    <?php endforeach; ?>
                    <?php if ($linkedProviders === []): ?>
                      <span class="small text-danger"><i class="bi bi-exclamation-circle me-1"></i>Sem profissional vinculado</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-service-<?= $serviceId ?>" aria-expanded="false">
                <i class="bi bi-pencil me-1"></i>Editar
              </button>
            </div>
          </div>

          <div class="collapse border-top" id="edit-service-<?= $serviceId ?>">
            <div class="card-body p-4 bg-body-tertiary">
              <form method="post" action="<?= e(url('/painel/servicos/' . $serviceId)) ?>">
                <?= Csrf::field() ?>
                <div class="row g-3">
                  <div class="col-md-7">
                    <label class="form-label">Nome</label>
                    <input class="form-control" name="name" maxlength="120" value="<?= e($service['name']) ?>" required>
                  </div>
                  <div class="col-md-5">
                    <label class="form-label">Status</label>
                    <div class="form-check form-switch service-active-switch mt-2">
                      <input class="form-check-input" type="checkbox" role="switch" name="active" id="active-<?= $serviceId ?>" <?= (int) $service['active'] === 1 ? 'checked' : '' ?>>
                      <label class="form-check-label" for="active-<?= $serviceId ?>">Disponível para agendamento</label>
                    </div>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Descrição</label>
                    <textarea class="form-control" name="description" rows="2" maxlength="1000"><?= e($service['description'] ?? '') ?></textarea>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label">Duração</label>
                    <div class="input-group">
                      <input class="form-control" type="number" name="duration_minutes" min="5" step="5" value="<?= (int) $service['duration_minutes'] ?>" required>
                      <span class="input-group-text">min</span>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <label class="form-label">Valor</label>
                    <div class="input-group">
                      <span class="input-group-text">R$</span>
                      <input class="form-control" name="price" inputmode="decimal" value="<?= e(number_format((float) $service['price'], 2, ',', '.')) ?>" required>
                    </div>
                  </div>
                </div>

                <div class="mt-4">
                  <label class="form-label d-block mb-2">Profissionais que realizam</label>
                  <div class="provider-selector">
                    <?php foreach ($providers as $provider): ?>
                      <?php $providerId = (int) $provider['id']; ?>
                      <label class="provider-option">
                        <input class="form-check-input" type="checkbox" name="provider_ids[]" value="<?= $providerId ?>" <?= in_array($providerId, $linkedIds, true) ? 'checked' : '' ?>>
                        <span>
                          <strong><?= e($provider['name']) ?></strong>
                          <small><?= ($provider['provider_role'] ?? '') === 'owner' ? 'Dono do estabelecimento' : 'Funcionário' ?></small>
                        </span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <div class="form-text">Ao remover um profissional, agendamentos já existentes não são alterados. Novos horários deixam de ser oferecidos para ele.</div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                  <button class="btn btn-dark" type="submit"><i class="bi bi-check2 me-2"></i>Salvar alterações</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if ($services === []): ?>
        <div class="card empty-state">
          <div class="card-body p-5 text-center">
            <div class="icon-box mx-auto mb-3"><i class="bi bi-scissors"></i></div>
            <h2 class="h5">Cadastre seu primeiro serviço</h2>
            <p class="text-muted-app mb-0">Ele aparecerá na sua página pública assim que estiver ativo e com um profissional vinculado.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card sticky-lg-top service-create-card" style="top: 88px">
      <div class="card-body p-4">
        <div class="icon-box icon-box-sm mb-3"><i class="bi bi-plus-lg"></i></div>
        <h2 class="h5 mb-1">Novo serviço</h2>
        <p class="small text-muted-app mb-4">Cadastre o serviço e escolha quem poderá recebê-lo na agenda.</p>

        <form method="post" action="<?= e(url('/painel/servicos')) ?>">
          <?= Csrf::field() ?>
          <div class="mb-3">
            <label class="form-label">Nome</label>
            <input class="form-control" name="name" maxlength="120" placeholder="Ex.: Corte feminino" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Descrição</label>
            <textarea class="form-control" name="description" rows="2" maxlength="1000" placeholder="Uma descrição curta para o cliente"></textarea>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-6">
              <label class="form-label">Duração</label>
              <div class="input-group">
                <input class="form-control" type="number" name="duration_minutes" min="5" step="5" value="30" required>
                <span class="input-group-text">min</span>
              </div>
            </div>
            <div class="col-6">
              <label class="form-label">Valor</label>
              <div class="input-group">
                <span class="input-group-text">R$</span>
                <input class="form-control" name="price" inputmode="decimal" value="0,00" required>
              </div>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label d-block mb-2">Quem realiza este serviço?</label>
            <div class="provider-selector">
              <?php foreach ($providers as $provider): ?>
                <?php $isOwner = ($provider['provider_role'] ?? '') === 'owner'; ?>
                <label class="provider-option">
                  <input class="form-check-input" type="checkbox" name="provider_ids[]" value="<?= (int) $provider['id'] ?>" <?= $isOwner ? 'checked' : '' ?>>
                  <span>
                    <strong><?= e($provider['name']) ?></strong>
                    <small><?= $isOwner ? 'Dono do estabelecimento' : 'Funcionário' ?></small>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="form-text">Todo serviço precisa de pelo menos um profissional. Se nenhum for marcado, o dono será vinculado automaticamente.</div>
          </div>

          <button class="btn btn-dark w-100" type="submit"><i class="bi bi-plus-lg me-2"></i>Cadastrar serviço</button>
        </form>
      </div>
    </div>
  </div>
</div>
