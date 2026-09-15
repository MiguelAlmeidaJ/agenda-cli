<?php use App\Core\Csrf; ?>
<?php
$isCreate = ($mode ?? 'create') === 'create';
$establishment = $establishment ?? [];
$streetValue = $establishment['street'] ?? ($establishment['address_line'] ?? '');
$hasMap = !$isCreate && $establishment !== [] && $establishment['latitude'] !== null && $establishment['longitude'] !== null;
?>
<section class="page-header">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
    <div>
      <div class="small text-muted-app mb-1">Administração</div>
      <h1 class="h3 mb-1"><?= $isCreate ? 'Novo estabelecimento' : 'Editar estabelecimento' ?></h1>
      <p class="text-muted-app mb-0"><?= $isCreate ? 'Crie o negócio e vincule a conta responsável.' : 'Atualize os dados gerais e o status do estabelecimento.' ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/admin/estabelecimentos')) ?>"><i class="bi bi-arrow-left me-2"></i>Voltar</a>
  </div>
</section>

<form method="post" action="<?= e($isCreate ? url('/admin/estabelecimentos') : url('/admin/estabelecimentos/' . $establishment['id'] . '/editar')) ?>">
  <?= Csrf::field() ?>
  <div class="row g-4 align-items-start">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-body p-4">
          <div class="d-flex align-items-center gap-2 mb-4"><span class="icon-box icon-box-sm"><i class="bi bi-shop"></i></span><h2 class="h5 mb-0">Dados do negócio</h2></div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Nome comercial</label>
              <input class="form-control" name="name" maxlength="150" value="<?= e($establishment['name'] ?? '') ?>" required>
            </div>
            <?php if (!$isCreate): ?>
              <div class="col-12">
                <label class="form-label">Link público</label>
                <div class="input-group"><span class="input-group-text">/estabelecimentos/</span><input class="form-control" value="<?= e($establishment['slug']) ?>" readonly></div>
                <div class="form-text">O endereço público permanece estável mesmo quando o nome comercial é alterado.</div>
              </div>
            <?php endif; ?>
            <div class="col-12">
              <label class="form-label">Descrição</label>
              <textarea class="form-control" name="description" rows="4" maxlength="3000" placeholder="Conte brevemente o que o estabelecimento oferece."><?= e($establishment['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Telefone</label>
              <input class="form-control" name="phone" maxlength="30" value="<?= e($establishment['phone'] ?? '') ?>" placeholder="(32) 99999-9999">
            </div>
            <div class="col-md-6">
              <label class="form-label">E-mail público</label>
              <input class="form-control" type="email" name="email" maxlength="190" value="<?= e($establishment['email'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body p-4">
          <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-2"><span class="icon-box icon-box-sm"><i class="bi bi-geo-alt"></i></span><div><h2 class="h5 mb-1">Localização</h2><div class="small text-muted-app">As coordenadas do mapa são calculadas automaticamente e não precisam ser informadas.</div></div></div>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">CEP</label>
              <input class="form-control" name="postal_code" inputmode="numeric" maxlength="10" value="<?= e($establishment['postal_code'] ?? '') ?>" placeholder="00000-000">
            </div>
            <div class="col-md-8">
              <label class="form-label">Logradouro</label>
              <input class="form-control" name="street" maxlength="150" value="<?= e($streetValue) ?>" placeholder="Rua, avenida, praça...">
            </div>
            <div class="col-md-3">
              <label class="form-label">Número</label>
              <input class="form-control" name="address_number" maxlength="30" value="<?= e($establishment['address_number'] ?? '') ?>" placeholder="123 ou S/N">
            </div>
            <div class="col-md-5">
              <label class="form-label">Complemento</label>
              <input class="form-control" name="complement" maxlength="100" value="<?= e($establishment['complement'] ?? '') ?>" placeholder="Sala, loja, bloco...">
            </div>
            <div class="col-md-4">
              <label class="form-label">Bairro</label>
              <input class="form-control" name="neighborhood" maxlength="100" value="<?= e($establishment['neighborhood'] ?? '') ?>">
            </div>
            <div class="col-md-8">
              <label class="form-label">Cidade</label>
              <input class="form-control" name="city" maxlength="100" value="<?= e($establishment['city'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">UF</label>
              <select class="form-select" name="state">
                <option value="">Selecione</option>
                <?php foreach ($states as $state): ?><option value="<?= e($state) ?>" <?= ($establishment['state'] ?? '') === $state ? 'selected' : '' ?>><?= e($state) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Fuso horário</label>
              <select class="form-select" name="timezone" required>
                <?php $selectedTimezone = $establishment['timezone'] ?? 'America/Sao_Paulo'; ?>
                <?php foreach ($timezones as $timezone => $label): ?><option value="<?= e($timezone) ?>" <?= $selectedTimezone === $timezone ? 'selected' : '' ?>><?= e($label) ?> — <?= e($timezone) ?></option><?php endforeach; ?>
              </select>
              <div class="form-text">O fuso é usado para agenda, bloqueios e horários especiais.</div>
            </div>
          </div>

          <?php if ($hasMap): ?>
            <div class="border-top mt-4 pt-4">
              <div class="d-flex justify-content-between align-items-center gap-3 mb-2"><div><div class="fw-semibold">Posição no mapa</div><div class="small text-muted-app">Gerada automaticamente a partir do endereço salvo.</div></div><span class="badge text-bg-light border"><i class="bi bi-geo-fill me-1"></i>Automático</span></div>
              <div class="establishment-map-preview" data-establishment-map data-latitude="<?= e($establishment['latitude']) ?>" data-longitude="<?= e($establishment['longitude']) ?>" data-name="<?= e($establishment['name']) ?>" data-zoom="16"></div>
            </div>
          <?php elseif (!$isCreate && !empty($establishment['address_line'])): ?>
            <div class="alert alert-light border mt-4 mb-0 small"><i class="bi bi-info-circle me-2"></i>O endereço atual ainda não possui coordenadas. Ao salvar o endereço estruturado, o sistema tentará posicioná-lo automaticamente no mapa.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-4 sticky-lg-top" style="top: 88px">
        <div class="card-body p-4">
          <?php if ($isCreate): ?>
            <div class="d-flex align-items-center gap-2 mb-3"><span class="icon-box icon-box-sm"><i class="bi bi-person-badge"></i></span><h2 class="h5 mb-0">Dono</h2></div>
            <p class="small text-muted-app">Use o e-mail de um dono já cadastrado sem estabelecimento ou informe os dados abaixo para criar uma nova conta.</p>
            <div class="mb-3">
              <label class="form-label">E-mail do dono</label>
              <input class="form-control" type="email" name="owner_email" list="available-owner-emails" maxlength="190" required>
              <datalist id="available-owner-emails"><?php foreach ($owners as $availableOwner): ?><option value="<?= e($availableOwner['email']) ?>"><?= e($availableOwner['name']) ?></option><?php endforeach; ?></datalist>
            </div>
            <div class="mb-3">
              <label class="form-label">Nome do dono <span class="text-muted-app fw-normal">(conta nova)</span></label>
              <input class="form-control" name="owner_name" maxlength="120">
            </div>
            <div class="mb-3">
              <label class="form-label">Telefone do dono <span class="text-muted-app fw-normal">(opcional)</span></label>
              <input class="form-control" name="owner_phone" maxlength="30">
            </div>
            <div class="mb-4">
              <label class="form-label">Senha inicial <span class="text-muted-app fw-normal">(conta nova)</span></label>
              <input class="form-control" type="password" name="owner_password" minlength="8" autocomplete="new-password">
              <div class="form-text">Necessária somente se o e-mail ainda não existir.</div>
            </div>
          <?php else: ?>
            <div class="small text-muted-app mb-1">Responsável</div>
            <h2 class="h5 mb-1"><?= e($owner['name']) ?></h2>
            <div class="small text-muted-app mb-4"><?= e($owner['email']) ?></div>
            <div class="alert alert-light border small">A troca de responsável não fica disponível nesta tela para preservar o vínculo do tenant e o histórico.</div>
          <?php endif; ?>

          <div class="form-check form-switch mb-4">
            <input class="form-check-input" type="checkbox" role="switch" id="active" name="active" <?= $isCreate || (int) ($establishment['active'] ?? 1) === 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="active">Estabelecimento ativo</label>
          </div>

          <button class="btn btn-dark w-100" type="submit"><i class="bi bi-check2 me-2"></i><?= $isCreate ? 'Criar estabelecimento' : 'Salvar alterações' ?></button>
          <?php if (!$isCreate): ?>
            <a class="btn btn-outline-secondary w-100 mt-2" target="_blank" rel="noopener" href="<?= e(url('/estabelecimentos/' . $establishment['slug'])) ?>"><i class="bi bi-box-arrow-up-right me-2"></i>Ver página pública</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</form>
