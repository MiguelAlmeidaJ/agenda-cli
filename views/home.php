<section class="home-hero">
  <div class="row align-items-center g-5">
    <div class="col-lg-6">
      <span class="home-kicker mb-3"><i class="bi bi-stars"></i> Agenda online para negócios de serviços</span>
      <h1 class="home-hero-title fw-bold mb-4">Sua agenda trabalha mesmo enquanto você está atendendo.</h1>
      <p class="home-hero-copy mb-4">Organize serviços, profissionais e horários em um só lugar. O AgendaCli calcula a disponibilidade, recebe agendamentos e reduz o trabalho repetitivo da sua equipe.</p>

      <div class="d-flex flex-column flex-sm-row gap-2 mb-4">
        <a class="btn btn-primary btn-lg px-4 home-primary-btn" href="<?= e(url('/cadastro')) ?>">Começar agora <i class="bi bi-arrow-right ms-2"></i></a>
        <a class="btn btn-outline-dark btn-lg px-4" href="#recursos">Ver como funciona</a>
      </div>

      <div class="home-benefits">
        <span><i class="bi bi-check-circle-fill"></i> Disponibilidade em tempo real</span>
        <span><i class="bi bi-check-circle-fill"></i> Regras por profissional</span>
        <span><i class="bi bi-check-circle-fill"></i> Agendamento 24 horas</span>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="home-product-stage">
        <div class="home-product-window">
          <div class="home-window-bar">
            <div class="home-window-dots"><span></span><span></span><span></span></div>
            <div class="home-window-label"><i class="bi bi-calendar2-week me-2"></i>Agenda de hoje</div>
            <span class="home-live-pill"><span></span> Atualizada</span>
          </div>

          <div class="home-product-content">
            <div class="home-product-sidebar">
              <div class="home-sidebar-logo"><i class="bi bi-calendar2-check"></i></div>
              <span class="active"><i class="bi bi-grid"></i></span>
              <span><i class="bi bi-calendar3"></i></span>
              <span><i class="bi bi-people"></i></span>
              <span><i class="bi bi-bar-chart"></i></span>
            </div>

            <div class="home-product-main">
              <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                  <span class="small text-muted-app">Quinta-feira, 24</span>
                  <h2 class="h5 fw-bold mb-0 mt-1">Agenda do Studio Aurora</h2>
                </div>
                <span class="home-count-badge">8 horários</span>
              </div>

              <div class="home-appointment-list">
                <div class="home-appointment">
                  <div class="home-appointment-time">09:00</div>
                  <div class="home-appointment-avatar">BR</div>
                  <div class="home-appointment-copy">
                    <strong>Beatriz Lima</strong>
                    <span>Corte & finalização · Rafael</span>
                  </div>
                  <span class="home-status home-status-confirmed"><i class="bi bi-check2"></i> Confirmado</span>
                </div>

                <div class="home-appointment">
                  <div class="home-appointment-time">10:30</div>
                  <div class="home-appointment-avatar">AC</div>
                  <div class="home-appointment-copy">
                    <strong>Ana Clara</strong>
                    <span>Manicure · Camila</span>
                  </div>
                  <span class="home-status home-status-presence"><i class="bi bi-person-check"></i> Presença</span>
                </div>

                <div class="home-appointment">
                  <div class="home-appointment-time">14:00</div>
                  <div class="home-appointment-avatar">JP</div>
                  <div class="home-appointment-copy">
                    <strong>João Pedro</strong>
                    <span>Hidratação premium · Rafael</span>
                  </div>
                  <span class="home-status home-status-pending"><i class="bi bi-clock"></i> Pendente</span>
                </div>
              </div>

              <div class="row g-2 mt-3">
                <div class="col-4"><div class="home-mini-metric"><strong>8</strong><span>agendamentos</span></div></div>
                <div class="col-4"><div class="home-mini-metric"><strong>6</strong><span>confirmados</span></div></div>
                <div class="col-4"><div class="home-mini-metric"><strong>2</strong><span>pendentes</span></div></div>
              </div>
            </div>
          </div>
        </div>

        <div class="home-floating-card home-floating-card-slots">
          <span class="home-floating-icon"><i class="bi bi-clock-history"></i></span>
          <div><strong>Horários calculados</strong><small>sem conflito de agenda</small></div>
        </div>

        <div class="home-floating-card home-floating-card-auto">
          <span class="home-floating-icon"><i class="bi bi-arrow-repeat"></i></span>
          <div><strong>Recorrência ativa</strong><small>4 atendimentos criados</small></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="home-discovery">
  <div class="home-discovery-card">
    <div class="home-discovery-copy">
      <span class="home-discovery-icon"><i class="bi bi-search"></i></span>
      <div>
        <strong>Você é cliente e quer marcar um horário?</strong>
        <span>Encontre estabelecimentos e veja horários realmente disponíveis.</span>
      </div>
    </div>

    <form class="home-search-form" method="get" action="<?= e(url('/encontre')) ?>">
      <div class="home-search-field">
        <i class="bi bi-search"></i>
        <input name="q" placeholder="Serviço ou estabelecimento" aria-label="Serviço ou estabelecimento">
      </div>
      <div class="home-search-field">
        <i class="bi bi-geo-alt"></i>
        <input name="city" placeholder="Cidade" aria-label="Cidade">
      </div>
      <button class="btn btn-dark" type="submit">Buscar <i class="bi bi-arrow-right ms-1"></i></button>
    </form>
  </div>
</section>

<section class="home-section" id="recursos">
  <div class="row align-items-end g-4 mb-4">
    <div class="col-lg-7">
      <span class="home-section-kicker">Menos trabalho manual</span>
      <h2 class="home-section-title fw-bold mb-2">Você configura as regras. O AgendaCli cuida do repetitivo.</h2>
      <p class="text-muted-app mb-0">A rotina fica organizada sem exigir que alguém passe o dia alimentando o sistema.</p>
    </div>
    <div class="col-lg-5 text-lg-end">
      <a class="home-text-link" href="<?= e(url('/cadastro')) ?>">Criar minha conta <i class="bi bi-arrow-right"></i></a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-6 col-lg-4">
      <article class="home-feature-card h-100">
        <div class="home-feature-icon"><i class="bi bi-calendar2-week"></i></div>
        <span class="home-feature-number">01</span>
        <h3>Agenda inteligente</h3>
        <p>Jornada, duração do serviço, bloqueios, férias e outros agendamentos entram automaticamente no cálculo de disponibilidade.</p>
      </article>
    </div>

    <div class="col-md-6 col-lg-4">
      <article class="home-feature-card h-100">
        <div class="home-feature-icon"><i class="bi bi-phone"></i></div>
        <span class="home-feature-number">02</span>
        <h3>Reserva online</h3>
        <p>O cliente encontra o serviço, escolhe um profissional e agenda apenas nos horários realmente livres.</p>
      </article>
    </div>

    <div class="col-md-6 col-lg-4">
      <article class="home-feature-card h-100">
        <div class="home-feature-icon"><i class="bi bi-arrow-repeat"></i></div>
        <span class="home-feature-number">03</span>
        <h3>Agendamentos recorrentes</h3>
        <p>Crie séries semanais em poucos cliques e valide todas as ocorrências antes de salvar.</p>
      </article>
    </div>

    <div class="col-md-6 col-lg-4">
      <article class="home-feature-card h-100">
        <div class="home-feature-icon"><i class="bi bi-person-check"></i></div>
        <span class="home-feature-number">04</span>
        <h3>Confirmação de presença</h3>
        <p>O cliente confirma pelo próprio painel ou por um link seguro, e a equipe enxerga a resposta no agendamento.</p>
      </article>
    </div>

    <div class="col-md-6 col-lg-4">
      <article class="home-feature-card h-100">
        <div class="home-feature-icon"><i class="bi bi-hourglass-split"></i></div>
        <span class="home-feature-number">05</span>
        <h3>Lista de espera</h3>
        <p>Quando surgir uma oportunidade, o sistema prepara uma oferta e revalida o horário antes da conversão.</p>
      </article>
    </div>

    <div class="col-md-6 col-lg-4">
      <article class="home-feature-card h-100">
        <div class="home-feature-icon"><i class="bi bi-people"></i></div>
        <span class="home-feature-number">06</span>
        <h3>Clientes e histórico</h3>
        <p>Centralize dados, últimos atendimentos, próximos horários e histórico para entender melhor sua base de clientes.</p>
      </article>
    </div>
  </div>
</section>

<section class="home-section home-flow-section" id="como-funciona">
  <div class="row g-5 align-items-center">
    <div class="col-lg-5">
      <span class="home-section-kicker">Fluxo simples</span>
      <h2 class="home-section-title fw-bold mb-3">Da configuração ao agendamento sem troca interminável de mensagens.</h2>
      <p class="text-muted-app mb-4">A disponibilidade nasce das regras reais do estabelecimento. O cliente vê somente aquilo que pode ser reservado.</p>
      <a class="btn btn-outline-dark" href="<?= e(url('/cadastro')) ?>">Configurar meu estabelecimento</a>
    </div>

    <div class="col-lg-7">
      <div class="home-flow">
        <div class="home-flow-item">
          <span>1</span>
          <div>
            <strong>Cadastre serviços, equipe e funcionamento</strong>
            <p>Defina duração, preço, profissionais e as faixas de atendimento de cada dia.</p>
          </div>
        </div>
        <div class="home-flow-line"></div>
        <div class="home-flow-item">
          <span>2</span>
          <div>
            <strong>Compartilhe a página do estabelecimento</strong>
            <p>Seu cliente encontra serviços, profissionais e horários disponíveis em uma experiência simples.</p>
          </div>
        </div>
        <div class="home-flow-line"></div>
        <div class="home-flow-item">
          <span>3</span>
          <div>
            <strong>Acompanhe tudo pelo painel</strong>
            <p>Agenda, clientes, recorrências, lista de espera e indicadores ficam organizados no mesmo lugar.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="home-client-section">
  <div class="home-client-card">
    <div class="home-client-decoration home-client-decoration-one"></div>
    <div class="home-client-decoration home-client-decoration-two"></div>
    <div class="row align-items-center g-4 position-relative">
      <div class="col-lg-7">
        <span class="home-dark-kicker"><i class="bi bi-compass"></i> Para quem quer agendar</span>
        <h2 class="fw-bold mb-2">Encontre um serviço e escolha o melhor horário.</h2>
        <p class="mb-0">Pesquise estabelecimentos, compare serviços e veja disponibilidade sem precisar perguntar por mensagem.</p>
      </div>
      <div class="col-lg-5 text-lg-end">
        <a class="btn btn-light btn-lg px-4" href="<?= e(url('/encontre')) ?>">Encontrar um serviço <i class="bi bi-arrow-right ms-2"></i></a>
      </div>
    </div>
  </div>
</section>

<section class="home-final-cta">
  <div class="home-final-inner">
    <span class="home-section-kicker">Uma rotina mais leve</span>
    <h2 class="fw-bold">Organize a agenda sem criar mais trabalho para sua equipe.</h2>
    <p>Comece configurando o estabelecimento e deixe as regras do sistema cuidarem da disponibilidade.</p>
    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
      <a class="btn btn-primary btn-lg px-4 home-primary-btn" href="<?= e(url('/cadastro')) ?>">Criar conta</a>
      <a class="btn btn-outline-dark btn-lg px-4" href="<?= e(url('/login')) ?>">Já tenho uma conta</a>
    </div>
  </div>
</section>
