<?php use App\Core\Csrf; ?>
<?php
$streetValue = $establishment['street'] ?? ($establishment['address_line'] ?? '');
$hasMap = $establishment['latitude'] !== null && $establishment['longitude'] !== null;
?>
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
          <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-2"><span class="icon-box icon-box-sm"><i class="bi bi-geo-alt"></i></span><div><h2 class="h5 mb-1">Localização</h2><div class="small text-muted-app">Preencha o endereço. O sistema encontra a posição do mapa automaticamente.</div></div></div>
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
          </div>

          <?php if ($hasMap): ?>
            <div class="border-top mt-4 pt-4">
              <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                <div><div class="fw-semibold">Como o cliente verá sua localização</div><div class="small text-muted-app">A posição é calculada no servidor sempre que o endereço muda.</div></div>
                <span class="badge text-bg-light border"><i class="bi bi-geo-fill me-1"></i>Automático</span>
              </div>
              <div class="establishment-map-preview" data-establishment-map data-latitude="<?= e($establishment['latitude']) ?>" data-longitude="<?= e($establishment['longitude']) ?>" data-name="<?= e($establishment['name']) ?>" data-zoom="16"></div>
            </div>
          <?php elseif (!empty($establishment['address_line'])): ?>
            <div class="alert alert-light border mt-4 mb-0 small"><i class="bi bi-info-circle me-2"></i>Seu endereço está salvo, mas ainda não possui posição no mapa. Revise os campos acima e salve para o sistema tentar localizar automaticamente.</div>
          <?php endif; ?>
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

          <div class="alert alert-light border small"><i class="bi bi-shield-check me-2"></i>Latitude e longitude são internas e não ficam disponíveis para edição manual.</div>
          <button class="btn btn-dark w-100" type="submit"><i class="bi bi-check2 me-2"></i>Salvar alterações</button>
        </div>
      </div>
    </div>
  </div>
</form>
