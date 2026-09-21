<?php

use App\Core\Csrf;

$statusLabels = [
    'waiting' => 'Aguardando vaga',
    'notified' => 'Vaga oferecida',
    'converted' => 'Convertida em agendamento',
    'cancelled' => 'Cancelada',
];
$periodLabels = [
    'any' => 'Qualquer horário',
    'morning' => 'Manhã',
    'afternoon' => 'Tarde',
    'evening' => 'Noite',
];
?>
<section class="page-header">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <div class="small text-muted-app mb-1">Minha agenda</div>
      <h1 class="h3 mb-1">Minha lista de espera</h1>
      <p class="text-muted-app mb-0">Acompanhe seus pedidos e confirme uma vaga quando ela aparecer.</p>
    </div>
    <a class="btn btn-dark btn-sm" href="<?= e(url('/encontre')) ?>"><i class="bi bi-search me-2"></i>Encontrar serviço</a>
  </div>
</section>

<div class="d-grid gap-3">
  <?php foreach ($entries as $entry): ?>
    <div class="card">
      <div class="card-body p-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
          <div class="flex-grow-1">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <h2 class="h5 mb-0"><?= e($entry['service_name']) ?></h2>
              <span class="badge text-bg-light border"><?= e($statusLabels[$entry['status']] ?? $entry['status']) ?></span>
            </div>
            <div class="text-muted-app mb-3">
              <a class="text-decoration-none" href="<?= e(url('/estabelecimentos/' . $entry['establishment_slug'])) ?>"><?= e($entry['establishment_name']) ?></a>
            </div>
            <div class="row g-3 small">
              <div class="col-sm-4"><span class="text-muted-app d-block">Data desejada</span><strong><?= e(date('d/m/Y', strtotime((string) $entry['desired_date']))) ?></strong></div>
              <div class="col-sm-4"><span class="text-muted-app d-block">Período</span><strong><?= e($periodLabels[$entry['time_period']] ?? $entry['time_period']) ?></strong></div>
              <div class="col-sm-4"><span class="text-muted-app d-block">Profissional</span><strong><?= e($entry['preferred_provider_name'] ?: 'Qualquer disponível') ?></strong></div>
            </div>

            <?php if (!empty($entry['match_id'])): ?>
              <div class="alert alert-success mt-4 mb-0">
                <div class="fw-semibold"><i class="bi bi-calendar-check me-2"></i>Vaga encontrada</div>
                <div class="small mt-1">
                  <?= e(date('d/m/Y H:i', strtotime((string) $entry['slot_start']))) ?>
                  com <?= e($entry['matched_provider_name']) ?>.
                  <?php if (!empty($entry['offer_expires_at'])): ?>
                    Confirme até <?= e(date('H:i', strtotime((string) $entry['offer_expires_at']))) ?>.
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <div class="d-flex flex-lg-column align-items-start align-items-lg-stretch justify-content-lg-center gap-2" style="min-width: 190px">
            <?php if (!empty($entry['match_id']) && in_array($entry['status'], ['waiting', 'notified'], true)): ?>
              <form method="post" action="<?= e(url('/painel/minha-lista-espera/' . $entry['id'] . '/aceitar')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="match_id" value="<?= (int) $entry['match_id'] ?>">
                <button class="btn btn-success w-100" type="submit"><i class="bi bi-check-circle me-2"></i>Confirmar vaga</button>
              </form>
            <?php endif; ?>

            <?php if (in_array($entry['status'], ['waiting', 'notified'], true)): ?>
              <form method="post" action="<?= e(url('/painel/minha-lista-espera/' . $entry['id'] . '/cancelar')) ?>" onsubmit="return confirm('Sair desta lista de espera?')">
                <?= Csrf::field() ?>
                <button class="btn btn-outline-danger w-100" type="submit">Sair da espera</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if ($entries === []): ?>
    <div class="card"><div class="card-body py-5 text-center">
      <i class="bi bi-hourglass-split fs-2 text-muted-app"></i>
      <h2 class="h5 mt-3">Nenhuma entrada na lista de espera</h2>
      <p class="text-muted-app mb-3">Quando um horário não servir, você pode registrar interesse na página do estabelecimento.</p>
      <a class="btn btn-dark" href="<?= e(url('/encontre')) ?>">Encontrar um serviço</a>
    </div></div>
  <?php endif; ?>
</div>
