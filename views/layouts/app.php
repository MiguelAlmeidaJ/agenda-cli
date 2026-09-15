<?php

use App\Core\Auth;
use App\Core\Csrf;

$user = Auth::user();
$success = flash('success');
$error = flash('error');
$firstName = $user ? (explode(' ', trim((string) $user['name']))[0] ?? $user['name']) : null;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Agenda online para estabelecimentos de serviços. Organize serviços, profissionais, horários e agendamentos em um só lugar.">
  <title><?= e($title ?? env('APP_NAME', 'Agenda CLI')) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(url('/assets/css/app.css')) ?>" rel="stylesheet">
  <link href="<?= e(url('/assets/css/experience.css')) ?>" rel="stylesheet">
  <link href="<?= e(url('/assets/css/operations.css')) ?>" rel="stylesheet">
  <link href="<?= e(url('/assets/css/crm.css')) ?>" rel="stylesheet">
  <link href="<?= e(url('/assets/css/management.css')) ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container container-narrow py-2">
    <a class="navbar-brand" href="<?= e(url('/')) ?>"><i class="bi bi-calendar2-check me-2"></i><?= e(env('APP_NAME', 'Agenda CLI')) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Abrir menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <div class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <?php if ($user): ?>
          <a class="nav-link" href="<?= e(url('/encontre')) ?>">Encontre</a>
          <a class="nav-link" href="<?= e(url('/painel')) ?>">Painel</a>
          <?php if (in_array(($user['role'] ?? null), ['owner', 'employee'], true)): ?>
            <a class="nav-link" href="<?= e(url('/painel/agenda')) ?>">Agenda</a>
            <a class="nav-link" href="<?= e(url('/painel/clientes')) ?>">Clientes</a>
            <a class="nav-link" href="<?= e(url('/painel/lista-espera')) ?>">Espera</a>
            <a class="nav-link" href="<?= e(url('/painel/notificacoes')) ?>">Notificações</a>
          <?php endif; ?>
          <a class="nav-link" href="<?= e(url('/painel/agendamentos')) ?>">Agendamentos</a>

          <?php if (($user['role'] ?? null) === 'owner'): ?>
            <div class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Gestão</a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(url('/painel/indicadores')) ?>"><i class="bi bi-bar-chart me-2"></i>Indicadores</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/painel/equipe')) ?>"><i class="bi bi-people me-2"></i>Equipe</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/painel/servicos')) ?>"><i class="bi bi-scissors me-2"></i>Serviços</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/painel/horarios')) ?>"><i class="bi bi-clock me-2"></i>Funcionamento</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= e(url('/painel/configuracoes/agendamento')) ?>"><i class="bi bi-sliders me-2"></i>Regras de agendamento</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/painel/notificacoes')) ?>"><i class="bi bi-chat-dots me-2"></i>Comunicação</a></li>
              </ul>
            </div>
          <?php endif; ?>

          <div class="nav-item dropdown ms-lg-2">
            <button class="btn btn-light btn-sm account-menu" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="account-avatar"><?= e(strtoupper(substr((string) $firstName, 0, 1))) ?></span>
              <span><?= e($firstName) ?></span>
              <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end account-dropdown">
              <li class="px-3 py-2"><div class="small fw-semibold"><?= e($user['name']) ?></div><div class="small text-muted-app"><?= e($user['email']) ?></div></li>
              <li><hr class="dropdown-divider"></li>
              <li><form method="post" action="<?= e(url('/logout')) ?>"><?= Csrf::field() ?><button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sair</button></form></li>
            </ul>
          </div>
        <?php else: ?>
          <a class="nav-link" href="<?= e(url('/#recursos')) ?>">Recursos</a>
          <a class="nav-link" href="<?= e(url('/encontre')) ?>">Encontre um serviço</a>
          <a class="btn btn-outline-secondary btn-sm ms-lg-2" href="<?= e(url('/login')) ?>">Entrar</a>
          <a class="btn btn-dark btn-sm" href="<?= e(url('/cadastro')) ?>">Criar conta</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<main class="container container-narrow pb-5">
  <?php if ($success): ?><div class="alert alert-success mt-3 mb-0"><?= e($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger mt-3 mb-0"><?= e($error) ?></div><?php endif; ?>
  <?= $content ?>
</main>

<footer class="site-footer">
  <div class="container container-narrow py-4 d-flex flex-column flex-md-row justify-content-between gap-2">
    <span class="small text-muted-app">© <?= date('Y') ?> <?= e(env('APP_NAME', 'Agenda CLI')) ?>. Agendamento simples para negócios de serviços.</span>
    <a class="small text-decoration-none text-muted-app" href="<?= e(url('/encontre')) ?>">Encontre um serviço</a>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('/assets/js/waitlist.js')) ?>"></script>
</body>
</html>
