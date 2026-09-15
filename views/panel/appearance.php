<?php use App\Core\Csrf; ?>
<section class="page-header">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
      <div class="small text-muted-app mb-1">Identidade visual</div>
      <h1 class="h3 mb-1">Aparência</h1>
      <p class="text-muted-app mb-0">Gerencie a capa e o logo exibidos na página pública do estabelecimento.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" href="<?= e(url('/estabelecimentos/' . $establishment['slug'])) ?>"><i class="bi bi-box-arrow-up-right me-2"></i>Ver página pública</a>
  </div>
</section>

<?php if (!$cloudinaryConfigured): ?>
  <div class="alert alert-warning"><i class="bi bi-cloud-slash me-2"></i>O Cloudinary ainda não está configurado no servidor. Configure as credenciais no <code>.env</code> antes de enviar imagens.</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card media-card h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
          <div><h2 class="h5 mb-1">Capa do estabelecimento</h2><p class="small text-muted-app mb-0">Recomendado: imagem horizontal com pelo menos 1400 × 500 px.</p></div>
          <span class="icon-box icon-box-sm"><i class="bi bi-image"></i></span>
        </div>
        <div class="media-cover-preview mb-3">
          <?php if (!empty($establishment['cover_url'])): ?>
            <img src="<?= e(cloudinary_image_url($establishment['cover_url'], 'c_fill,w_1400,h_500,q_auto,f_auto')) ?>" alt="Capa de <?= e($establishment['name']) ?>">
          <?php else: ?>
            <div class="media-empty-preview"><i class="bi bi-image"></i><span>Nenhuma capa cadastrada</span></div>
          <?php endif; ?>
        </div>
        <form method="post" enctype="multipart/form-data" action="<?= e(url('/painel/aparencia/capa')) ?>" class="d-flex flex-column flex-md-row gap-2">
          <?= Csrf::field() ?>
          <input class="form-control" type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" required>
          <button class="btn btn-dark text-nowrap" type="submit"><i class="bi bi-cloud-arrow-up me-2"></i><?= empty($establishment['cover_url']) ? 'Enviar capa' : 'Trocar capa' ?></button>
        </form>
        <?php if (!empty($establishment['cover_url'])): ?>
          <form method="post" action="<?= e(url('/painel/aparencia/capa/remover')) ?>" class="mt-2" onsubmit="return confirm('Remover a capa do estabelecimento?')">
            <?= Csrf::field() ?><button class="btn btn-link btn-sm text-danger px-0" type="submit"><i class="bi bi-trash3 me-1"></i>Remover capa</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card media-card h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
          <div><h2 class="h5 mb-1">Logo</h2><p class="small text-muted-app mb-0">Prefira uma imagem quadrada, com fundo limpo.</p></div>
          <span class="icon-box icon-box-sm"><i class="bi bi-shop"></i></span>
        </div>
        <div class="media-logo-preview mb-3">
          <?php if (!empty($establishment['logo_url'])): ?>
            <img src="<?= e(cloudinary_image_url($establishment['logo_url'], 'c_fill,w_320,h_320,q_auto,f_auto')) ?>" alt="Logo de <?= e($establishment['name']) ?>">
          <?php else: ?>
            <div class="media-empty-logo"><?= e(strtoupper(substr((string) $establishment['name'], 0, 1))) ?></div>
          <?php endif; ?>
        </div>
        <form method="post" enctype="multipart/form-data" action="<?= e(url('/painel/aparencia/logo')) ?>" class="d-grid gap-2">
          <?= Csrf::field() ?>
          <input class="form-control" type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" required>
          <button class="btn btn-dark" type="submit"><i class="bi bi-cloud-arrow-up me-2"></i><?= empty($establishment['logo_url']) ? 'Enviar logo' : 'Trocar logo' ?></button>
        </form>
        <?php if (!empty($establishment['logo_url'])): ?>
          <form method="post" action="<?= e(url('/painel/aparencia/logo/remover')) ?>" class="mt-2" onsubmit="return confirm('Remover o logo do estabelecimento?')">
            <?= Csrf::field() ?><button class="btn btn-link btn-sm text-danger px-0" type="submit"><i class="bi bi-trash3 me-1"></i>Remover logo</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="small text-muted-app mt-3"><i class="bi bi-info-circle me-1"></i>JPG, PNG, WebP, GIF ou AVIF, até 5 MB. Ao trocar ou remover uma imagem, o arquivo anterior também é excluído do Cloudinary; falhas temporárias entram na fila automática de limpeza.</div>
