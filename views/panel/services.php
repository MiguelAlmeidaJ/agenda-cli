<?php use App\Core\Csrf; ?>
<section class="page-header">
  <div>
    <div class="small text-muted-app mb-1">Configuração</div>
    <h1 class="h3 mb-0">Serviços</h1>
  </div>
</section>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th class="ps-4">Serviço</th><th>Duração</th><th>Valor</th><th class="pe-4">Status</th></tr></thead>
            <tbody>
            <?php foreach ($services as $service): ?>
              <tr>
                <td class="ps-4"><div class="fw-semibold"><?= e($service['name']) ?></div><div class="small text-muted-app"><?= e($service['description'] ?? '') ?></div></td>
                <td><?= (int) $service['duration_minutes'] ?> min</td>
                <td>R$ <?= e(number_format((float) $service['price'], 2, ',', '.')) ?></td>
                <td class="pe-4"><span class="badge badge-soft"><?= (int) $service['active'] === 1 ? 'Ativo' : 'Inativo' ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if ($services === []): ?><tr><td colspan="4" class="text-center text-muted-app py-5">Nenhum serviço cadastrado.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Novo serviço</h2>
        <form method="post" action="<?= e(url('/painel/servicos')) ?>">
          <?= Csrf::field() ?>
          <div class="mb-3"><label class="form-label">Nome</label><input class="form-control" name="name" maxlength="120" required></div>
          <div class="mb-3"><label class="form-label">Descrição</label><textarea class="form-control" name="description" rows="2"></textarea></div>
          <div class="row g-3 mb-3">
            <div class="col-6"><label class="form-label">Duração</label><div class="input-group"><input class="form-control" type="number" name="duration_minutes" min="5" step="5" value="30" required><span class="input-group-text">min</span></div></div>
            <div class="col-6"><label class="form-label">Valor</label><div class="input-group"><span class="input-group-text">R$</span><input class="form-control" name="price" inputmode="decimal" value="0,00" required></div></div>
          </div>

          <?php if ($employees !== []): ?>
            <div class="mb-4">
              <label class="form-label d-block">Profissionais que realizam</label>
              <?php foreach ($employees as $employee): ?>
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="employee_ids[]" value="<?= (int) $employee['id'] ?>" id="employee-<?= (int) $employee['id'] ?>" checked>
                  <label class="form-check-label" for="employee-<?= (int) $employee['id'] ?>"><?= e($employee['name']) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <button class="btn btn-dark w-100">Cadastrar serviço</button>
        </form>
      </div>
    </div>
  </div>
</div>
