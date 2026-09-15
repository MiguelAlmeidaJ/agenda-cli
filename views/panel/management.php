<?php
$maxTrend = 0.0;
foreach ($trend as $item) $maxTrend = max($maxTrend, (float) $item['revenue']);
$monthNames = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];
?>
<section class="page-header">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
    <div>
      <div class="small text-muted-app mb-1">Gestão</div>
      <h1 class="h3 mb-1">Indicadores do negócio</h1>
      <p class="text-muted-app mb-0">Resultado do mês atual e sinais que pedem atenção.</p>
    </div>
    <div class="small text-muted-app"><i class="bi bi-calendar3 me-1"></i><?= e(date('m/Y')) ?></div>
  </div>
</section>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="management-metric h-100"><span class="management-icon"><i class="bi bi-cash-stack"></i></span><small>Faturamento concluído</small><strong>R$ <?= e(number_format((float)$metrics['revenue'],2,',','.')) ?></strong><span class="<?= $metrics['revenue_delta'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= $metrics['revenue_delta'] >= 0 ? '+' : '' ?><?= e(number_format((float)$metrics['revenue_delta'],1,',','.')) ?>% vs. mês anterior</span></div></div>
  <div class="col-sm-6 col-xl-3"><div class="management-metric h-100"><span class="management-icon"><i class="bi bi-check2-circle"></i></span><small>Atendimentos concluídos</small><strong><?= (int)$metrics['completed'] ?></strong><span><?= (int)$metrics['new_customers'] ?> novo(s) cliente(s)</span></div></div>
  <div class="col-sm-6 col-xl-3"><div class="management-metric h-100"><span class="management-icon"><i class="bi bi-person-x"></i></span><small>Taxa de falta</small><strong><?= e(number_format((float)$metrics['no_show_rate'],1,',','.')) ?>%</strong><span>Cancelamentos: <?= e(number_format((float)$metrics['cancellation_rate'],1,',','.')) ?>%</span></div></div>
  <div class="col-sm-6 col-xl-3"><div class="management-metric h-100"><span class="management-icon"><i class="bi bi-hourglass-split"></i></span><small>Lista de espera</small><strong><?= (int)$metrics['waitlist'] ?></strong><span><?= (int)$metrics['matches'] ?> vaga(s) compatível(is)</span></div></div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-7">
    <div class="card management-card h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="h5 mb-1">Faturamento dos últimos meses</h2><div class="small text-muted-app">Somente atendimentos concluídos</div></div></div>
        <?php if ($trend): ?>
          <div class="management-trend">
            <?php foreach ($trend as $item): ?>
              <?php [$year,$month] = explode('-', (string)$item['period']); $height = $maxTrend > 0 ? max(8, ((float)$item['revenue']/$maxTrend)*100) : 8; ?>
              <div class="trend-column" title="R$ <?= e(number_format((float)$item['revenue'],2,',','.')) ?>">
                <div class="trend-value">R$ <?= e(number_format((float)$item['revenue'],0,',','.')) ?></div>
                <div class="trend-track"><div class="trend-bar" style="height:<?= e(number_format($height,1,'.','')) ?>%"></div></div>
                <div class="trend-label"><?= e($monthNames[$month] ?? $month) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center text-muted-app py-5">Ainda não há atendimentos concluídos para formar o histórico.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card management-card h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-1">Operação agora</h2><div class="small text-muted-app">Pendências que podem virar atendimento</div></div></div>
        <a class="management-operation" href="<?= e(url('/painel/lista-espera')) ?>"><span><i class="bi bi-hourglass-split"></i></span><div><strong><?= (int)$metrics['waitlist'] ?> na lista de espera</strong><small><?= (int)$metrics['matches'] ?> com vaga encontrada</small></div><i class="bi bi-chevron-right ms-auto"></i></a>
        <a class="management-operation" href="<?= e(url('/painel/notificacoes')) ?>"><span><i class="bi bi-chat-dots"></i></span><div><strong><?= (int)$metrics['pending_notifications'] ?> notificações pendentes</strong><small>Mensagens prontas para contato</small></div><i class="bi bi-chevron-right ms-auto"></i></a>
        <a class="management-operation" href="<?= e(url('/painel/agenda')) ?>"><span><i class="bi bi-calendar3"></i></span><div><strong>Abrir agenda</strong><small>Acompanhar o movimento de hoje</small></div><i class="bi bi-chevron-right ms-auto"></i></a>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6"><div class="card management-card h-100"><div class="card-body p-4"><h2 class="h5 mb-1">Serviços com mais saída</h2><p class="small text-muted-app mb-3">Atendimentos concluídos neste mês</p><?php foreach ($topServices as $item): ?><div class="management-ranking"><div><strong><?= e($item['name']) ?></strong><small><?= (int)$item['total'] ?> atendimento(s)</small></div><span>R$ <?= e(number_format((float)$item['revenue'],2,',','.')) ?></span></div><?php endforeach; ?><?php if (!$topServices): ?><div class="small text-muted-app py-4">Sem dados neste mês.</div><?php endif; ?></div></div></div>
  <div class="col-lg-6"><div class="card management-card h-100"><div class="card-body p-4"><h2 class="h5 mb-1">Desempenho da equipe</h2><p class="small text-muted-app mb-3">Atendimentos concluídos neste mês</p><?php foreach ($topProfessionals as $item): ?><div class="management-ranking"><div><strong><?= e($item['name']) ?></strong><small><?= (int)$item['total'] ?> atendimento(s)</small></div><span>R$ <?= e(number_format((float)$item['revenue'],2,',','.')) ?></span></div><?php endforeach; ?><?php if (!$topProfessionals): ?><div class="small text-muted-app py-4">Sem dados neste mês.</div><?php endif; ?></div></div></div>
</div>
