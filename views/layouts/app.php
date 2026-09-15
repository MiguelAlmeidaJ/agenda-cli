<?php

use App\Core\Auth;
use App\Core\Csrf;

$user = Auth::user();
$success = flash('success');
$error = flash('error');
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
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container container-narrow py-2">
    <a class="navbar-brand" href="<?= e(url('/')) ?>"><i class="bi bi-calendar2-check me-2"></i><?= e(env('APP_NAME', 'Agenda CLI')) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Abrir menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <a class="nav-link" href="<?= e(url('/#recursos')) ?>">Recursos</a>
        <a class="nav-link" href="<?= e(url('/encontre')) ?>">Encontre um serviço</a>
        <?php if ($user): ?>
          <a class="nav-link" href="<?= e(url('/painel')) ?>">Painel</a>
          <a class="nav-link" href="<?= e(url('/painel/agendamentos')) ?>">Agendamentos</a>
          <?php if (($user['role'] ?? null) === 'owner'): ?>
            <a class="nav-link" href="<?= e(url('/painel/servicos')) ?>">Serviços</a>
            <a class="nav-link" href="<?= e(url('/painel/horarios')) ?>">Horários</a>
          <?php endif; ?>
          <form method="post" action="<?= e(url('/logout')) ?>" class="ms-lg-2">
            <?= Csrf::field() ?>
            <button class="btn btn-outline-secondary btn-sm">Sair</button>
          </form>
        <?php else: ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/login')) ?>">Entrar</a>
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
</body>
</html>
