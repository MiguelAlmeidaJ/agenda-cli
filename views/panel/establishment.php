<?php use App\Core\Csrf; ?>
<section class="page-header">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
    <div>
      <div class="small text-muted-app mb-1">Gestão</div>
      <h1 class="h3 mb-1">Meu estabelecimento</h1>
      <p class="text-muted-app mb-0">Atualize as informações públicas, contato, localização e fuso usados pela agenda.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="<?= e(url('/painel/aparencia')) ?>"><i class="bi bi-images me-2"></i>Logo e capa</a>
      <a class="btn btn-outline-dark" target="_blank" rel="noopener" href="<?= e(url('/estabelecimentos/' . $establishment['slug'])) ?>"><i class="bi bi-box-arrow-up-right me-2"></i>Ver página pública</a>
    </div>
  </div>
</section>

<form method="post" action="<?= e(url('/painel/estabelecimento')) ?>">
  <?= Csrf::field() ?>
  <div class="row g-4 align-items-start">
    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-body p-4">
          <div class="d-flex align-items-center gap-2 mb-4"><span class="icon-box icon-box-sm"><i class="bi bi-shop"></i></span><h2 class="h5 mb-0">Informações públicas</h2></div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Nome comercial</label>
              <input class="form-control" name="name" maxlength="150" value="<?= e($establishment['name']) ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label">Descrição</label>
              <textarea class="form-control" name="description" rows="4" maxlength="3000" placeholder="Conte aos clientes o que o estabelecimento oferece."><?= e($establishment['description'] ?? '') ?></textarea>
              <div class="form-text">Essa descrição aparece na página pública do estabelecimento.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Telefone</label>
              <input class="form-control" name="phone" maxlength="30" value="<?= e($establishment['phone'] ?? '') ?>" placeholder="(32) 99999-9999">
            </div>
            <div class="col-md-6">
              <label class="form-label">E-mail do estabelecimento</label>
              <input class="form-control" type="email" name="email" maxlength="190" value="<?= e($establishment['email'] ?? '') ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body p-4">
          <div class="d-flex align-items-center gap-2 mb-4"><span class="icon-box icon-box-sm"><i class="bi bi-geo-alt"></i></span><h2 class="h5 mb-0">Localização</h2></div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Endereço</label>
              <input class="form-control" name="address_line" maxlength="190" value="<?= e($establishment['address_line'] ?? '') ?>" placeholder="Rua, número, complemento e bairro">
              <div class="form-text">Informe o endereço da forma como deseja que apareça para o cliente.</div>
            </div>
            <div class="col-md-7">
              <label class="form-label">Cidade</label>
              <input class="form-control" name="city" maxlength="100" value="<?= e($establishment['city'] ?? '') ?>">
            </div>
            <div class="col-md-5">
              <label class="form-label">UF</label>
              <select class="form-select" name="state">
                <option value="">Selecione</option>
                <?php foreach ($states as $state): ?><option value="<?= e($state) ?>" <?= ($establishment['state'] ?? '') === $state ? 'selected' : '' ?>><?= e($state) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card sticky-lg-top" style="top: 88px">
        <div class="card-body p-4">
          <div class="small text-muted-app mb-1">Configuração da agenda</div>
          <h2 class="h5 mb-3">Fuso horário</h2>
          <select class="form-select mb-3" name="timezone" required>
            <?php foreach ($timezones as $timezone => $label): ?><option value="<?= e($timezone) ?>" <?= ($establishment['timezone'] ?? 'America/Sao_Paulo') === $timezone ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
          <p class="small text-muted-app">Horários, bloqueios, feriados e agendamentos usam este fuso. Altere apenas se o estabelecimento realmente operar em outra região.</p>

          <div class="border-top pt-3 mt-3 mb-4">
            <div class="small text-muted-app">Link público</div>
            <div class="small fw-semibold text-break">/estabelecimentos/<?= e($establishment['slug']) ?></div>
            <div class="form-text">O link não muda quando você altera o nome comercial.</div>
          </div>

          <button class="btn btn-dark w-100" type="submit"><i class="bi bi-check2 me-2"></i>Salvar alterações</button>
        </div>
      </div>
    </div>
  </div>
</form>
