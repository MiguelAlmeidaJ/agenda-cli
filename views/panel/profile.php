<?php

use App\Core\Csrf;

$roleLabels = [
    'admin' => 'Administrador',
    'owner' => 'Dono',
    'employee' => 'Profissional',
    'client' => 'Cliente',
];
$memberSince = !empty($profile['created_at']) ? date('m/Y', strtotime((string) $profile['created_at'])) : null;
?>
<section class="page-header">
  <div>
    <div class="small text-muted-app mb-1">Conta</div>
    <h1 class="h3 mb-1">Meu perfil</h1>
    <p class="text-muted-app mb-0">Atualize seus dados pessoais e a imagem usada na sua conta.</p>
  </div>
</section>

<?php if (!$cloudinaryConfigured): ?>
  <div class="alert alert-warning"><i class="bi bi-cloud-slash me-2"></i>O Cloudinary ainda não está configurado no servidor. Seus dados podem ser editados normalmente, mas o envio de foto ficará indisponível.</div>
<?php endif; ?>

<div class="row g-4 align-items-start profile-layout">
  <div class="col-lg-4">
    <div class="card media-card profile-photo-card sticky-lg-top" style="top: 92px">
      <div class="card-body p-4 text-center">
        <div class="profile-photo-preview mx-auto mb-3">
          <?php if (!empty($profile['avatar_url'])): ?>
            <img src="<?= e(cloudinary_image_url($profile['avatar_url'], 'c_fill,w_360,h_360,g_face,q_auto,f_auto')) ?>" alt="Foto de <?= e($profile['name']) ?>">
          <?php else: ?>
            <span><?= e(strtoupper(substr((string) $profile['name'], 0, 1))) ?></span>
          <?php endif; ?>
        </div>
        <h2 class="h5 mb-1"><?= e($profile['name']) ?></h2>
        <div class="small text-muted-app"><?= e($profile['email']) ?></div>
        <div class="d-flex justify-content-center flex-wrap gap-2 mt-3 mb-3">
          <span class="badge badge-soft"><?= e($roleLabels[$profile['role']] ?? ucfirst((string) $profile['role'])) ?></span>
          <?php if ($memberSince): ?><span class="badge text-bg-light border">Desde <?= e($memberSince) ?></span><?php endif; ?>
        </div>
        <a class="btn btn-outline-secondary btn-sm mb-4" href="<?= e(url('/painel/perfil/senha')) ?>"><i class="bi bi-key me-2"></i>Alterar senha</a>

        <form method="post" enctype="multipart/form-data" action="<?= e(url('/painel/perfil/foto')) ?>" class="d-grid gap-2 text-start">
          <?= Csrf::field() ?>
          <label class="form-label mb-0">Foto de perfil</label>
          <input class="form-control" type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" required <?= $cloudinaryConfigured ? '' : 'disabled' ?>>
          <div class="form-text">JPG, PNG, WebP, GIF ou AVIF, até 5 MB.</div>
          <button class="btn btn-dark mt-1" type="submit" <?= $cloudinaryConfigured ? '' : 'disabled' ?>><i class="bi bi-cloud-arrow-up me-2"></i><?= empty($profile['avatar_url']) ? 'Enviar foto' : 'Trocar foto' ?></button>
        </form>

        <?php if (!empty($profile['avatar_url'])): ?>
          <form method="post" action="<?= e(url('/painel/perfil/foto/remover')) ?>" class="mt-2" onsubmit="return confirm('Remover sua foto de perfil?')">
            <?= Csrf::field() ?><button class="btn btn-link btn-sm text-danger" type="submit"><i class="bi bi-trash3 me-1"></i>Remover foto</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card profile-details-card">
      <div class="card-body p-4 p-xl-5">
        <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
          <div>
            <div class="small text-muted-app mb-1">Informações pessoais</div>
            <h2 class="h5 mb-1">Dados da conta</h2>
            <p class="small text-muted-app mb-0">Esses dados são privados e não são publicados automaticamente na página do estabelecimento.</p>
          </div>
          <span class="icon-box icon-box-sm"><i class="bi bi-person"></i></span>
        </div>

        <form method="post" action="<?= e(url('/painel/perfil')) ?>">
          <?= Csrf::field() ?>
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label">Nome completo</label>
              <input class="form-control" name="name" maxlength="120" value="<?= e($profile['name']) ?>" autocomplete="name" required>
            </div>
            <div class="col-md-5">
              <label class="form-label">Data de nascimento</label>
              <input class="form-control" type="date" name="birth_date" value="<?= e($profile['birth_date'] ?? '') ?>" max="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="col-md-7">
              <label class="form-label">E-mail</label>
              <input class="form-control" type="email" name="email" maxlength="190" value="<?= e($profile['email']) ?>" autocomplete="email" required>
              <div class="form-text">Esse e-mail também é usado para entrar na sua conta.</div>
            </div>
            <div class="col-md-5">
              <label class="form-label">Telefone</label>
              <input class="form-control" name="phone" maxlength="30" value="<?= e($profile['phone'] ?? '') ?>" autocomplete="tel" placeholder="(32) 99999-9999">
            </div>
            <div class="col-md-8">
              <label class="form-label">Cidade</label>
              <input class="form-control" name="city" maxlength="100" value="<?= e($profile['city'] ?? '') ?>" autocomplete="address-level2" placeholder="Sua cidade">
            </div>
            <div class="col-md-4">
              <label class="form-label">UF</label>
              <select class="form-select" name="state" autocomplete="address-level1">
                <option value="">Selecione</option>
                <?php foreach ($states as $state): ?>
                  <option value="<?= e($state) ?>" <?= ($profile['state'] ?? '') === $state ? 'selected' : '' ?>><?= e($state) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Sobre você</label>
              <textarea class="form-control" name="bio" rows="4" maxlength="500" placeholder="Conte um pouco sobre você, sua experiência ou especialidades."><?= e($profile['bio'] ?? '') ?></textarea>
              <div class="form-text">Até 500 caracteres. Para profissionais, essa bio poderá ser usada futuramente na apresentação pública da equipe.</div>
            </div>
          </div>

          <div class="profile-privacy-note mt-4">
            <i class="bi bi-shield-check"></i>
            <div><strong>Privacidade</strong><span>Data de nascimento, telefone e localização permanecem restritos à sua conta. Não exibiremos esses campos publicamente sem uma configuração específica.</span></div>
          </div>

          <div class="d-flex justify-content-end mt-4">
            <button class="btn btn-dark" type="submit"><i class="bi bi-check2 me-2"></i>Salvar dados pessoais</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
