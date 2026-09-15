<?php use App\Core\Csrf; ?>
<?php
$statusLabels = ['pending'=>'Pendente','sent'=>'Enviada','failed'=>'Falhou','cancelled'=>'Cancelada'];
$eventLabels = ['appointment_confirmation'=>'Confirmação','appointment_cancelled'=>'Cancelamento','reminder_24h'=>'Lembrete 24h','reminder_2h'=>'Lembrete 2h','waitlist_slot_available'=>'Vaga da lista de espera'];
$provider = (string) ($settings['provider'] ?? 'manual');
?>
<section class="page-header">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
    <div><div class="small text-muted-app mb-1">Comunicação</div><h1 class="h3 mb-1">Notificações</h1><p class="text-muted-app mb-0">Prepare e envie confirmações, lembretes, cancelamentos e avisos da lista de espera.</p></div>
    <div class="d-flex flex-wrap gap-2">
      <form method="post" action="<?= e(url('/painel/notificacoes/preparar')) ?>"><?= Csrf::field() ?><button class="btn btn-dark btn-sm" type="submit"><i class="bi bi-arrow-repeat me-2"></i>Processar agora</button></form>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/painel/indicadores')) ?>"><i class="bi bi-bar-chart me-2"></i>Indicadores</a>
    </div>
  </div>
</section>

<div class="row g-4 align-items-start">
  <div class="col-lg-4">
    <div class="card management-card mb-4"><div class="card-body p-4">
      <div class="d-flex align-items-center gap-3 mb-3">
        <span class="notification-channel-icon"><i class="bi bi-whatsapp"></i></span>
        <div><h2 class="h5 mb-0">WhatsApp</h2><div class="small text-muted-app"><?= (int)$settings['whatsapp_enabled'] === 1 ? ($provider === 'evolution_go' ? 'Envio automático ativo' : 'Preparação ativa') : 'Desativado' ?></div></div>
      </div>

      <?php if ($provider === 'evolution_go'): ?>
        <div class="alert <?= $evolutionConfigured ? 'alert-success' : 'alert-warning' ?> small py-2">
          <i class="bi <?= $evolutionConfigured ? 'bi-check-circle' : 'bi-exclamation-triangle' ?> me-1"></i>
          <?= $evolutionConfigured ? 'Evolution GO configurada no servidor.' : 'Falta configurar a Evolution GO no .env do servidor.' ?>
        </div>
      <?php endif; ?>

      <?php if ($role === 'owner'): ?>
        <form method="post" action="<?= e(url('/painel/notificacoes/configuracoes')) ?>">
          <?= Csrf::field() ?>
          <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="whatsapp_enabled" id="whatsapp_enabled" <?= (int)$settings['whatsapp_enabled']===1?'checked':'' ?>><label class="form-check-label" for="whatsapp_enabled">Ativar mensagens de WhatsApp</label></div>
          <label class="form-label small" for="provider">Modo de envio</label>
          <select class="form-select mb-3" name="provider" id="provider">
            <option value="manual" <?= $provider==='manual'?'selected':'' ?>>Manual</option>
            <option value="evolution_go" <?= $provider==='evolution_go'?'selected':'' ?>>Evolution GO — automático</option>
            <option value="meta_cloud" <?= $provider==='meta_cloud'?'selected':'' ?>>Meta Cloud API — futuro</option>
            <option value="custom" <?= $provider==='custom'?'selected':'' ?>>Gateway personalizado</option>
          </select>
          <?php foreach ([
            'confirmation_enabled'=>'Confirmação do agendamento',
            'cancellation_enabled'=>'Cancelamento',
            'reminder_24h_enabled'=>'Lembrete 24h antes',
            'reminder_2h_enabled'=>'Lembrete 2h antes',
            'waitlist_enabled'=>'Vaga da lista de espera',
          ] as $field=>$label): ?>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="<?= e($field) ?>" id="<?= e($field) ?>" <?= (int)$settings[$field]===1?'checked':'' ?>><label class="form-check-label small" for="<?= e($field) ?>"><?= e($label) ?></label></div>
          <?php endforeach; ?>
          <button class="btn btn-dark w-100 mt-3" type="submit">Salvar preferências</button>
        </form>
      <?php else: ?>
        <p class="small text-muted-app mb-0">Somente o dono pode alterar as preferências de comunicação.</p>
      <?php endif; ?>
    </div></div>

    <div class="notification-note"><i class="bi bi-shield-check"></i><div><strong>Token fora do sistema</strong><p class="small mb-0">A credencial da Evolution GO fica em arquivo protegido no servidor, fora do banco, do diretório público e do Git.</p></div></div>
  </div>

  <div class="col-lg-8">
    <div class="row g-3 mb-3">
      <div class="col-4"><div class="notification-stat"><small>Pendentes</small><strong><?= (int)($counts['pending']??0) ?></strong></div></div>
      <div class="col-4"><div class="notification-stat"><small>Enviadas</small><strong><?= (int)($counts['sent']??0) ?></strong></div></div>
      <div class="col-4"><div class="notification-stat"><small>Falhas</small><strong><?= (int)($counts['failed']??0) ?></strong></div></div>
    </div>
    <div class="card management-card"><div class="card-body p-0">
      <div class="p-4 border-bottom"><h2 class="h5 mb-1">Caixa de saída</h2><div class="small text-muted-app"><?= $provider === 'evolution_go' ? 'A Evolution GO envia automaticamente as mensagens pendentes. O botão manual continua disponível como contingência.' : 'No modo manual, abra a conversa e marque a mensagem como enviada depois do contato.' ?></div></div>
      <?php foreach ($outbox as $item): ?>
        <?php $waUrl = 'https://wa.me/' . preg_replace('/\D+/', '', (string)$item['recipient']) . '?text=' . rawurlencode((string)$item['message']); ?>
        <div class="notification-row">
          <div class="notification-main">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-1"><strong><?= e($item['customer_name']) ?></strong><span class="badge badge-soft"><?= e($eventLabels[$item['event_type']]??$item['event_type']) ?></span><span class="badge badge-soft"><?= e($statusLabels[$item['status']]??$item['status']) ?></span></div>
            <p class="small mb-1"><?= e($item['message']) ?></p>
            <div class="small text-muted-app"><?= e($item['recipient']) ?> · <?= e(date('d/m H:i', strtotime((string)$item['scheduled_at']))) ?><?php if (!empty($item['provider_message_id'])): ?> · ID <?= e($item['provider_message_id']) ?><?php endif; ?></div>
            <?php if ($item['status'] === 'failed' && !empty($item['last_error'])): ?><div class="small text-danger mt-1"><i class="bi bi-exclamation-circle me-1"></i><?= e($item['last_error']) ?></div><?php endif; ?>
          </div>
          <?php if (in_array($item['status'], ['pending','failed'], true)): ?><div class="notification-actions"><a class="btn btn-sm btn-outline-dark" href="<?= e($waUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i>Abrir</a><form method="post" action="<?= e(url('/painel/notificacoes/' . $item['id'] . '/enviada')) ?>"><?= Csrf::field() ?><button class="btn btn-sm btn-dark" type="submit">Marcar enviada</button></form></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$outbox): ?><div class="text-center text-muted-app py-5"><i class="bi bi-chat-square-text d-block fs-3 mb-2"></i>Nenhuma mensagem preparada ainda.</div><?php endif; ?>
    </div></div>
  </div>
</div>
