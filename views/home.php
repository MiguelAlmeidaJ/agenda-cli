<section class="hero">
  <div class="row align-items-end g-4">
    <div class="col-lg-8">
      <span class="badge badge-soft mb-3">Agendamento simples, sem ligação</span>
      <h1 class="display-5 fw-bold mb-3">Encontre um serviço e reserve seu horário.</h1>
      <p class="lead text-muted-app mb-0">Veja estabelecimentos, serviços, valores e disponibilidade em um só lugar.</p>
    </div>
  </div>
</section>

<div class="row g-3">
  <?php foreach ($establishments as $establishment): ?>
    <div class="col-md-6 col-lg-4">
      <a class="text-decoration-none text-reset" href="<?= e(url('/estabelecimentos/' . $establishment['slug'])) ?>">
        <div class="card card-hover h-100">
          <div class="card-body p-4">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
              <div>
                <h2 class="h5 mb-1"><?= e($establishment['name']) ?></h2>
                <div class="small text-muted-app"><i class="bi bi-geo-alt me-1"></i><?= e(trim(($establishment['city'] ?? '') . ' ' . ($establishment['state'] ?? ''))) ?></div>
              </div>
              <i class="bi bi-arrow-up-right"></i>
            </div>
            <p class="text-muted-app mb-4"><?= e($establishment['description'] ?: 'Conheça os serviços e horários disponíveis.') ?></p>
            <div class="d-flex justify-content-between small">
              <span><?= (int) $establishment['service_count'] ?> serviço(s)</span>
              <?php if ($establishment['min_price'] !== null): ?><span>A partir de R$ <?= e(number_format((float) $establishment['min_price'], 2, ',', '.')) ?></span><?php endif; ?>
            </div>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>

  <?php if ($establishments === []): ?>
    <div class="col-12"><div class="card"><div class="card-body p-5 text-center text-muted-app">Nenhum estabelecimento disponível no momento.</div></div></div>
  <?php endif; ?>
</div>
