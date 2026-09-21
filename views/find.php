<?php

$hasFilters = $filters['q'] !== '' || $filters['city'] !== '' || $filters['state'] !== '';
$resultCount = count($establishments);
?>
<section class="page-header find-header">
  <div class="row align-items-end g-4">
    <div class="col-lg-8">
      <span class="eyebrow mb-3">Agende online</span>
      <h1 class="display-6 fw-bold mb-3">Encontre o serviço certo para você.</h1>
      <p class="lead text-muted-app mb-0">Pesquise por serviço ou estabelecimento, filtre sua cidade e veja primeiro o que está mais perto.</p>
    </div>
  </div>
</section>

<div class="card public-search-card mb-4">
  <div class="card-body p-4">
    <form method="get" action="<?= e(url('/encontre')) ?>" class="row g-3 align-items-end" id="public-search-form">
      <div class="col-lg-5">
        <label class="form-label fw-semibold" for="search-q">Serviço ou estabelecimento</label>
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input class="form-control" id="search-q" name="q" value="<?= e($filters['q']) ?>" list="service-suggestions" placeholder="Ex.: corte, manicure, fisioterapia">
        </div>
        <datalist id="service-suggestions">
          <?php foreach ($serviceSuggestions as $serviceName): ?>
            <option value="<?= e($serviceName) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>

      <div class="col-md-6 col-lg-3">
        <label class="form-label fw-semibold" for="search-city">Cidade</label>
        <input class="form-control" id="search-city" name="city" value="<?= e($filters['city']) ?>" list="city-suggestions" placeholder="Ex.: Juiz de Fora">
        <datalist id="city-suggestions">
          <?php foreach ($citySuggestions as $cityName): ?>
            <option value="<?= e($cityName) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>

      <div class="col-md-3 col-lg-2">
        <label class="form-label fw-semibold" for="search-state">UF</label>
        <select class="form-select" id="search-state" name="state">
          <option value="">Todas</option>
          <?php foreach ($stateSuggestions as $state): ?>
            <option value="<?= e($state) ?>" <?= $filters['state'] === $state ? 'selected' : '' ?>><?= e($state) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3 col-lg-2 d-grid">
        <button class="btn btn-dark" type="submit"><i class="bi bi-search me-2"></i>Buscar</button>
      </div>

      <div class="col-md-6 col-lg-3">
        <label class="form-label fw-semibold" for="search-sort">Ordenar por</label>
        <select class="form-select" id="search-sort" name="sort" onchange="this.form.submit()">
          <option value="relevance" <?= $filters['sort'] === 'relevance' ? 'selected' : '' ?>>Relevância</option>
          <option value="name" <?= $filters['sort'] === 'name' ? 'selected' : '' ?>>Nome</option>
          <option value="price" <?= $filters['sort'] === 'price' ? 'selected' : '' ?>>Menor preço inicial</option>
        </select>
      </div>

      <div class="col-md-6 col-lg-4 d-flex flex-wrap gap-2 align-items-end">
        <button class="btn btn-outline-secondary" type="button" id="use-location">
          <i class="bi bi-crosshair me-2"></i>Usar minha localização
        </button>
        <?php if ($hasFilters): ?>
          <a class="btn btn-link text-decoration-none" href="<?= e(url('/encontre')) ?>">Limpar filtros</a>
        <?php endif; ?>
      </div>

      <div class="col-lg-5">
        <div class="small text-muted-app" id="location-status">Sua localização é usada somente no navegador para ordenar os resultados desta página.</div>
      </div>
    </form>
  </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <strong><?= $resultCount ?> resultado<?= $resultCount === 1 ? '' : 's' ?></strong>
    <?php if ($hasFilters): ?>
      <span class="text-muted-app small ms-1">para os filtros selecionados</span>
    <?php endif; ?>
  </div>
  <?php if ($filters['q'] !== ''): ?>
    <span class="badge text-bg-light border">Busca: <?= e($filters['q']) ?></span>
  <?php endif; ?>
</div>

<div class="row g-3" id="public-search-results">
  <?php foreach ($establishments as $index => $establishment): ?>
    <?php
      $serviceNames = array_slice((array) ($establishment['service_names'] ?? []), 0, 4);
      $hasCoordinates = $establishment['latitude'] !== null && $establishment['longitude'] !== null;
    ?>
    <div class="col-md-6 col-lg-4 public-search-result"
         data-result-card
         data-original-order="<?= $index ?>"
         data-name="<?= e(strtolower((string) $establishment['name'])) ?>"
         data-lat="<?= $hasCoordinates ? e((string) $establishment['latitude']) : '' ?>"
         data-lng="<?= $hasCoordinates ? e((string) $establishment['longitude']) : '' ?>">
      <a class="text-decoration-none text-reset" href="<?= e(url('/estabelecimentos/' . $establishment['slug'])) ?>">
        <div class="card card-hover h-100 overflow-hidden public-search-result-card">
          <?php if (!empty($establishment['cover_url'])): ?>
            <div class="find-establishment-cover"><img src="<?= e(cloudinary_image_url($establishment['cover_url'], 'c_fill,w_720,h_340,q_auto,f_auto')) ?>" alt="Capa de <?= e($establishment['name']) ?>"></div>
          <?php endif; ?>

          <div class="card-body p-4 d-flex flex-column">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
              <div class="d-flex align-items-center gap-3 min-w-0">
                <?php if (!empty($establishment['logo_url'])): ?>
                  <span class="find-establishment-logo"><img src="<?= e(cloudinary_image_url($establishment['logo_url'], 'c_fit,w_120,h_120,q_auto,f_auto')) ?>" alt=""></span>
                <?php endif; ?>
                <div class="min-w-0">
                  <h2 class="h5 mb-1 text-truncate"><?= e($establishment['name']) ?></h2>
                  <div class="small text-muted-app">
                    <i class="bi bi-geo-alt me-1"></i><?= e(trim(($establishment['city'] ?? '') . (($establishment['city'] ?? '') && ($establishment['state'] ?? '') ? ' - ' : '') . ($establishment['state'] ?? ''))) ?>
                  </div>
                </div>
              </div>
              <span class="icon-box icon-box-sm"><i class="bi bi-arrow-up-right"></i></span>
            </div>

            <div class="distance-badge mb-3 d-none" data-distance-badge>
              <i class="bi bi-crosshair me-1"></i><span data-distance-value></span>
            </div>

            <p class="text-muted-app mb-3"><?= e($establishment['description'] ?: 'Conheça os serviços e horários disponíveis.') ?></p>

            <?php if ($serviceNames !== []): ?>
              <div class="public-search-service-tags mb-4">
                <?php foreach ($serviceNames as $serviceName): ?>
                  <span><?= e($serviceName) ?></span>
                <?php endforeach; ?>
                <?php if ((int) $establishment['service_count'] > count($serviceNames)): ?>
                  <span>+<?= (int) $establishment['service_count'] - count($serviceNames) ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between gap-3 small border-top pt-3 mt-auto">
              <span><?= (int) $establishment['service_count'] ?> serviço(s)</span>
              <?php if ($establishment['min_price'] !== null): ?>
                <span class="fw-semibold">A partir de R$ <?= e(number_format((float) $establishment['min_price'], 2, ',', '.')) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>

  <?php if ($establishments === []): ?>
    <div class="col-12">
      <div class="card empty-state">
        <div class="card-body p-5 text-center">
          <div class="icon-box mx-auto mb-3"><i class="bi bi-search"></i></div>
          <h2 class="h5">Nenhum resultado encontrado</h2>
          <p class="text-muted-app mb-3">Tente remover algum filtro ou buscar por um termo mais amplo.</p>
          <a class="btn btn-outline-dark" href="<?= e(url('/encontre')) ?>">Ver todos os estabelecimentos</a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
(() => {
  const button = document.getElementById('use-location');
  const status = document.getElementById('location-status');
  const container = document.getElementById('public-search-results');
  if (!button || !status || !container) return;

  function distanceKm(lat1, lng1, lat2, lng2) {
    const toRad = value => value * Math.PI / 180;
    const earthRadiusKm = 6371;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2
      + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  function showDistance(card, distance) {
    card.dataset.distance = String(distance);
    const badge = card.querySelector('[data-distance-badge]');
    const value = card.querySelector('[data-distance-value]');
    if (!badge || !value) return;
    value.textContent = distance < 10 ? distance.toFixed(1) + ' km' : Math.round(distance) + ' km';
    badge.classList.remove('d-none');
  }

  button.addEventListener('click', () => {
    if (!navigator.geolocation) {
      status.textContent = 'Seu navegador não oferece acesso à localização.';
      return;
    }

    button.disabled = true;
    status.textContent = 'Obtendo sua localização...';

    navigator.geolocation.getCurrentPosition(position => {
      const userLat = position.coords.latitude;
      const userLng = position.coords.longitude;
      const cards = [...container.querySelectorAll('[data-result-card]')];
      let located = 0;

      cards.forEach(card => {
        const lat = Number(card.dataset.lat);
        const lng = Number(card.dataset.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng) || card.dataset.lat === '' || card.dataset.lng === '') {
          card.dataset.distance = '';
          return;
        }
        showDistance(card, distanceKm(userLat, userLng, lat, lng));
        located++;
      });

      cards.sort((a, b) => {
        const aDistance = a.dataset.distance === '' ? Number.POSITIVE_INFINITY : Number(a.dataset.distance);
        const bDistance = b.dataset.distance === '' ? Number.POSITIVE_INFINITY : Number(b.dataset.distance);
        if (aDistance !== bDistance) return aDistance - bDistance;
        return a.dataset.name.localeCompare(b.dataset.name, 'pt-BR');
      });
      cards.forEach(card => container.appendChild(card));

      if (located > 0) {
        status.textContent = 'Resultados com endereço geocodificado ordenados pela distância até você.';
        button.innerHTML = '<i class="bi bi-check-circle me-2"></i>Mais próximos primeiro';
      } else {
        status.textContent = 'Nenhum dos resultados atuais possui coordenadas para calcular distância.';
      }
      button.disabled = false;
    }, error => {
      status.textContent = error.code === 1
        ? 'A permissão de localização não foi concedida.'
        : 'Não foi possível obter sua localização agora.';
      button.disabled = false;
    }, {
      enableHighAccuracy: false,
      timeout: 8000,
      maximumAge: 300000
    });
  });
})();
</script>
