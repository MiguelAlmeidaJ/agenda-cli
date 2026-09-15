<?php use App\Core\Csrf; ?>
<?php
$statusLabels = ['waiting'=>'Aguardando','notified'=>'Avisado','converted'=>'Convertido','cancelled'=>'Cancelado'];
$periodLabels = ['any'=>'Qualquer horário','morning'=>'Manhã','afternoon'=>'Tarde','evening'=>'Noite'];
?>
<section class="page-header">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div><div class="small text-muted-app mb-1">Agenda</div><h1 class="h3 mb-1">Lista de espera</h1><p class="text-muted-app mb-0">O sistema cruza automaticamente pedidos de espera com horários livres.</p></div>
    <div class="d-flex gap-2">
      <form method="post" action="<?= e(url('/painel/lista-espera/verificar')) ?>"><?= Csrf::field() ?><button class="btn btn-dark btn-sm" type="submit"><i class="bi bi-arrow-repeat me-2"></i>Verificar vagas agora</button></form>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/painel/notificacoes')) ?>"><i class="bi bi-chat-dots me-2"></i>Notificações</a>
    </div>
  </div>
</section>

<div class="card"><div class="card-body p-0"><div class="table-responsive">
  <table class="table align-middle mb-0">
    <thead><tr><th class="ps-4">Data desejada</th><th>Cliente</th><th>Serviço</th><th>Preferência</th><th>Vaga encontrada</th><th>Status</th><th class="pe-4 text-end">Ação</th></tr></thead>
    <tbody>
      <?php foreach ($entries as $entry): ?>
        <tr>
          <td class="ps-4"><div class="fw-semibold"><?= e(date('d/m/Y', strtotime((string)$entry['desired_date']))) ?></div><div class="small text-muted-app"><?= e($periodLabels[$entry['time_period']]??'Qualquer horário') ?></div></td>
          <td><div class="fw-semibold"><?= e($entry['customer_name']) ?></div><div class="small text-muted-app"><?= e($entry['customer_phone']?:$entry['customer_email']?:'Sem contato informado') ?></div></td>
          <td><?= e($entry['service_name']) ?></td>
          <td><?= e($entry['provider_name']?:'Qualquer profissional') ?></td>
          <td><?php if (!empty($entry['matched_slot'])): ?><span class="waitlist-match"><i class="bi bi-lightning-charge"></i><?= e(date('d/m H:i', strtotime((string)$entry['matched_slot']))) ?></span><div class="small text-muted-app mt-1"><?= e($entry['matched_provider_name']??'') ?></div><?php else: ?><span class="small text-muted-app">Aguardando vaga</span><?php endif; ?></td>
          <td><span class="badge badge-soft"><?= e($statusLabels[$entry['status']]??$entry['status']) ?></span></td>
          <td class="pe-4 text-end"><form class="d-inline-flex gap-2" method="post" action="<?= e(url('/painel/lista-espera/' . $entry['id'] . '/status')) ?>"><?= Csrf::field() ?><select class="form-select form-select-sm" name="status"><?php foreach ($statusLabels as $value=>$label): ?><option value="<?= e($value) ?>" <?= $entry['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-dark" type="submit">Salvar</button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$entries): ?><tr><td colspan="7" class="text-center text-muted-app py-5"><i class="bi bi-list-check d-block fs-3 mb-2"></i>Ninguém na lista de espera no momento.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div></div></div>
