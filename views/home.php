<section class="sales-hero">
  <div class="row align-items-center g-5">
    <div class="col-lg-7">
      <span class="eyebrow mb-3">Agenda online para negócios de serviços</span>
      <h1 class="display-4 fw-bold mb-4">Sua agenda organizada. Seus clientes agendando sem depender de mensagem.</h1>
      <p class="lead text-muted-app mb-4">Centralize serviços, profissionais, horários e agendamentos em um sistema simples para sua equipe e fácil para seus clientes.</p>
      <div class="d-flex flex-column flex-sm-row gap-2">
        <a class="btn btn-dark btn-lg px-4" href="#recursos">Conhecer recursos</a>
        <a class="btn btn-outline-dark btn-lg px-4" href="<?= e(url('/encontre')) ?>"><i class="bi bi-search me-2"></i>Encontrar um serviço</a>
      </div>
      <div class="d-flex flex-wrap gap-4 mt-4 small text-muted-app">
        <span><i class="bi bi-check2 me-1"></i>Agenda em tempo real</span>
        <span><i class="bi bi-check2 me-1"></i>Multiestabelecimento</span>
        <span><i class="bi bi-check2 me-1"></i>Acesso por perfil</span>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="product-preview">
        <div class="preview-topbar d-flex align-items-center justify-content-between">
          <div>
            <div class="small text-muted-app">Hoje</div>
            <div class="fw-semibold">Agenda do estabelecimento</div>
          </div>
          <span class="badge text-bg-light border">8 agendamentos</span>
        </div>
        <div class="preview-body">
          <div class="preview-appointment">
            <div class="preview-time">09:00</div>
            <div>
              <div class="fw-semibold">Corte masculino</div>
              <div class="small text-muted-app">Rafael · 45 min</div>
            </div>
            <i class="bi bi-check-circle ms-auto text-success"></i>
          </div>
          <div class="preview-appointment">
            <div class="preview-time">10:30</div>
            <div>
              <div class="fw-semibold">Manicure</div>
              <div class="small text-muted-app">Camila · 60 min</div>
            </div>
            <i class="bi bi-check-circle ms-auto text-success"></i>
          </div>
          <div class="preview-appointment">
            <div class="preview-time">13:00</div>
            <div>
              <div class="fw-semibold">Consulta</div>
              <div class="small text-muted-app">Mariana · 30 min</div>
            </div>
            <i class="bi bi-clock ms-auto text-muted-app"></i>
          </div>
          <div class="preview-summary row g-2 mt-2">
            <div class="col-6"><div class="preview-stat"><strong>6</strong><span>confirmados</span></div></div>
            <div class="col-6"><div class="preview-stat"><strong>2</strong><span>pendentes</span></div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="sales-section" id="recursos">
  <div class="section-heading">
    <span class="eyebrow mb-3">Tudo em um só lugar</span>
    <h2 class="fw-bold">Menos tempo organizando a agenda. Mais tempo atendendo.</h2>
    <p class="text-muted-app">O Agenda CLI organiza a rotina do estabelecimento sem adicionar complexidade à operação.</p>
  </div>

  <div class="row g-3 mt-2">
    <div class="col-md-6 col-lg-3">
      <div class="feature-card h-100">
        <div class="icon-box mb-4"><i class="bi bi-calendar2-week"></i></div>
        <h3 class="h5">Agenda centralizada</h3>
        <p class="text-muted-app mb-0">Visualize os horários do negócio e acompanhe os atendimentos em um único painel.</p>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="feature-card h-100">
        <div class="icon-box mb-4"><i class="bi bi-scissors"></i></div>
        <h3 class="h5">Serviços e duração</h3>
        <p class="text-muted-app mb-0">Cadastre serviços, valores e tempo necessário para cada atendimento.</p>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="feature-card h-100">
        <div class="icon-box mb-4"><i class="bi bi-people"></i></div>
        <h3 class="h5">Equipe organizada</h3>
        <p class="text-muted-app mb-0">Funcionários ficam vinculados ao estabelecimento e aos serviços que realizam.</p>
      </div>
    </div>
    <div class="col-md-6 col-lg-3">
      <div class="feature-card h-100">
        <div class="icon-box mb-4"><i class="bi bi-phone"></i></div>
        <h3 class="h5">Reserva online</h3>
        <p class="text-muted-app mb-0">O cliente vê apenas horários realmente disponíveis e agenda de forma simples.</p>
      </div>
    </div>
  </div>
</section>

<section class="sales-section border-top" id="como-funciona">
  <div class="row g-5 align-items-center">
    <div class="col-lg-5">
      <span class="eyebrow mb-3">Como funciona</span>
      <h2 class="fw-bold mb-3">Do cadastro do serviço ao horário reservado.</h2>
      <p class="text-muted-app mb-0">A disponibilidade é montada a partir dos horários do estabelecimento, duração dos serviços, profissionais e agendamentos já existentes.</p>
    </div>
    <div class="col-lg-7">
      <div class="steps-list">
        <div class="step-item">
          <span class="step-number">01</span>
          <div><h3 class="h6 mb-1">Configure o estabelecimento</h3><p class="text-muted-app mb-0">Defina serviços, equipe e horário de funcionamento.</p></div>
        </div>
        <div class="step-item">
          <span class="step-number">02</span>
          <div><h3 class="h6 mb-1">O cliente escolhe o melhor horário</h3><p class="text-muted-app mb-0">Serviço, profissional e disponibilidade ficam acessíveis online.</p></div>
        </div>
        <div class="step-item">
          <span class="step-number">03</span>
          <div><h3 class="h6 mb-1">Sua equipe acompanha pelo painel</h3><p class="text-muted-app mb-0">Cada perfil acessa somente o que precisa para trabalhar.</p></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="sales-section">
  <div class="client-callout">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <span class="eyebrow eyebrow-light mb-3">Para quem quer agendar</span>
        <h2 class="fw-bold mb-2">Procurando um serviço?</h2>
        <p class="mb-0 text-white-50">Veja estabelecimentos disponíveis, compare serviços e reserve seu horário online.</p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <a class="btn btn-light btn-lg px-4" href="<?= e(url('/encontre')) ?>">Encontre um serviço <i class="bi bi-arrow-right ms-2"></i></a>
      </div>
    </div>
  </div>
</section>

<section class="sales-section pt-0">
  <div class="text-center py-5">
    <span class="eyebrow mb-3">Uma rotina mais simples</span>
    <h2 class="fw-bold mb-3">Sua agenda não precisa depender de planilha e troca de mensagens.</h2>
    <p class="text-muted-app mx-auto mb-4 closing-copy">Centralize a operação e deixe o cliente encontrar horários disponíveis sem interromper o atendimento.</p>
    <a class="btn btn-dark btn-lg px-4" href="<?= e(url('/login')) ?>">Acessar a plataforma</a>
  </div>
</section>
