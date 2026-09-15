<section class="page-header find-header">
  <div class="row align-items-end g-4">
    <div class="col-lg-8">
      <span class="eyebrow mb-3">Agende online</span>
      <h1 class="display-6 fw-bold mb-3">Encontre um serviço perto de você.</h1>
      <p class="lead text-muted-app mb-0">Escolha um estabelecimento, veja os serviços disponíveis e reserve o melhor horário.</p>
    </div>
  </div>
</section>

<div class="row g-3">
  <?php foreach ($establishments as $establishment): ?>
    <div class="col-md-6 col-lg-4">
      <a class="text-decoration-none text-reset" href="<?= e(url('/estabelecimentos/' . $establishment['slug'])) ?>">
        <div class="card card-hover h-100 overflow-hidden">
          <?php if (!empty($establishment['cover_url'])): ?>
            <div class="find-establishment-cover"><img src="<?= e(cloudinary_image_url($establishment['cover_url'], 'c_fill,w_720,h_340,q_auto,f_auto')) ?>" alt="Capa de <?= e($establishment['name']) ?>"></div>
          <?php endif; ?>
          <div class="card-body p-4">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
              <div class="d-flex align-items-center gap-3">
                <?php if (!empty($establishment['logo_url'])): ?>
                  <span class="find-establishment-logo"><img src="<?= e(cloudinary_image_url($establishment['logo_url'], 'c_fill,w_120,h_120,q_auto,f_auto')) ?>" alt=""></span>
                <?php endif; ?>
                <div>
                  <h2 class="h5 mb-1"><?= e($establishment['name']) ?></h2>
                  <div class="small text-muted-app">
                    <i class="bi bi-geo-alt me-1"></i><?= e(trim(($establishment['city'] ?? '') . ' ' . ($establishment['state'] ?? ''))) ?>
                  </div>
                </div>
              </div>
              <span class="icon-box icon-box-sm"><i class="bi bi-arrow-up-right"></i></span>
            </div>
            <p class="text-muted-app mb-4"><?= e($establishment['description'] ?: 'Conheça os serviços e horários disponíveis.') ?></p>
            <div class="d-flex justify-content-between gap-3 small border-top pt-3">
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
          <div class="icon-box mx-auto mb-3"><i class="bi bi-calendar2-x"></i></div>
          <h2 class="h5">Nenhum estabelecimento disponível</h2>
          <p class="text-muted-app mb-0">Novos estabelecimentos aparecerão aqui assim que estiverem disponíveis para agendamento.</p>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
