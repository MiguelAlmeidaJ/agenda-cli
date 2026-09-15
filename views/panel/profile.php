<?php use App\Core\Csrf; ?>
<section class="page-header">
  <div>
    <div class="small text-muted-app mb-1">Conta</div>
    <h1 class="h3 mb-1">Meu perfil</h1>
    <p class="text-muted-app mb-0">Gerencie a imagem usada na sua conta e nas áreas onde você aparece como profissional.</p>
  </div>
</section>

<?php if (!$cloudinaryConfigured): ?>
  <div class="alert alert-warning"><i class="bi bi-cloud-slash me-2"></i>O Cloudinary ainda não está configurado no servidor.</div>
<?php endif; ?>

<div class="row g-4 align-items-start">
  <div class="col-lg-5">
    <div class="card media-card">
      <div class="card-body p-4 text-center">
        <div class="profile-photo-preview mx-auto mb-3">
          <?php if (!empty($profile['avatar_url'])): ?>
            <img src="<?= e(cloudinary_image_url($profile['avatar_url'], 'c_fill,w_360,h_360,g_face,q_auto,f_auto')) ?>" alt="Foto de <?= e($profile['name']) ?>">
          <?php else: ?>
            <span><?= e(strtoupper(substr((string) $profile['name'], 0, 1))) ?></span>
          <?php endif; ?>
        </div>
        <h2 class="h5 mb-1"><?= e($profile['name']) ?></h2>
        <div class="small text-muted-app mb-4"><?= e($profile['email']) ?></div>

        <form method="post" enctype="multipart/form-data" action="<?= e(url('/painel/perfil/foto')) ?>" class="d-grid gap-2 text-start">
          <?= Csrf::field() ?>
          <label class="form-label mb-0">Nova foto</label>
          <input class="form-control" type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" required>
          <button class="btn btn-dark mt-1" type="submit"><i class="bi bi-cloud-arrow-up me-2"></i><?= empty($profile['avatar_url']) ? 'Enviar foto' : 'Trocar foto' ?></button>
        </form>

        <?php if (!empty($profile['avatar_url'])): ?>
          <form method="post" action="<?= e(url('/painel/perfil/foto/remover')) ?>" class="mt-2" onsubmit="return confirm('Remover sua foto de perfil?')">
            <?= Csrf::field() ?><button class="btn btn-link btn-sm text-danger" type="submit"><i class="bi bi-trash3 me-1"></i>Remover foto</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Dados da conta</h2>
        <div class="row g-3">
          <div class="col-md-6"><div class="small text-muted-app">Nome</div><div class="fw-semibold"><?= e($profile['name']) ?></div></div>
          <div class="col-md-6"><div class="small text-muted-app">E-mail</div><div class="fw-semibold"><?= e($profile['email']) ?></div></div>
          <div class="col-md-6"><div class="small text-muted-app">Telefone</div><div class="fw-semibold"><?= e($profile['phone'] ?: 'Não informado') ?></div></div>
          <div class="col-md-6"><div class="small text-muted-app">Perfil</div><div class="fw-semibold"><?= e(ucfirst((string) $profile['role'])) ?></div></div>
        </div>
      </div>
    </div>
    <div class="small text-muted-app mt-3"><i class="bi bi-info-circle me-1"></i>Formatos aceitos: JPG, PNG, WebP, GIF e AVIF, até 5 MB. Ao trocar a foto, a anterior é removida do Cloudinary.</div>
  </div>
</div>
