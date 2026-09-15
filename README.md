# Agenda CLI

Sistema web multi-tenant de agendamentos feito em PHP + MySQL, com interface em Bootstrap.

## Perfis

- **Admin**: visão global da plataforma.
- **Dono do estabelecimento**: gerencia serviços, equipe, horários, clientes e agenda do estabelecimento.
- **Funcionário**: vinculado a um estabelecimento, com agenda e horários próprios.
- **Cliente**: navega pelos estabelecimentos e agenda horários.

## Stack

- PHP 8.3+
- MySQL 8+
- PDO / `pdo_mysql`
- Composer / PSR-4
- Bootstrap 5.3 + Bootstrap Icons
- Apache com `mod_rewrite` ou Nginx equivalente

## Requisitos do servidor

- PHP 8.3 ou superior
- Extensão `pdo_mysql`
- MySQL 8 ou superior
- Composer 2
- Servidor web apontando o DocumentRoot para a pasta `public/`
- No Apache, `mod_rewrite` habilitado e `AllowOverride All` para o `.htaccess`

## Instalação

Clone o projeto no servidor e instale as dependências:

```bash
git clone https://github.com/MiguelAlmeidaJ/agenda-cli.git
cd agenda-cli
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

Edite o `.env` com o domínio e as credenciais reais do MySQL:

```env
APP_ENV=production
APP_URL=https://agenda.seu-dominio.com.br

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=agenda
DB_USERNAME=agenda
DB_PASSWORD=sua-senha-segura
```

Crie o banco e importe a estrutura:

```bash
mysql -u agenda -p agenda < database/schema.sql
```

Para carregar os dados de demonstração em ambiente de desenvolvimento, execute:

```bash
php database/seed.php
```

> Em produção, não execute o seed de demonstração.

## Atualizações de banco

Para instalações que já possuem o banco criado, aplique as migrations novas da pasta `database/migrations/` na ordem em que foram adicionadas.

Gestão de feriados, fechamentos e horários excepcionais:

```bash
mysql -u usuario -p banco < database/migrations/2026_09_15_create_special_hours.sql
```

Agenda individual por profissional e histórico operacional de agendamentos:

```bash
mysql -u usuario -p banco < database/migrations/2026_09_15_operations_v2.sql
```

CRM de clientes e agendamento manual:

```bash
mysql -u usuario -p banco < database/migrations/2026_09_15_customers_and_manual_booking.sql
```

Regras de reserva e lista de espera:

```bash
mysql -u usuario -p banco < database/migrations/2026_09_15_booking_rules_and_waitlist.sql
```

A migration de clientes cria a entidade operacional `customers`, torna `client_user_id` opcional nos agendamentos e vincula automaticamente os agendamentos antigos aos clientes correspondentes.

### Apache

Configure o VirtualHost para que o `DocumentRoot` aponte para a pasta `public/` do projeto. Exemplo:

```apache
<VirtualHost *:80>
    ServerName agenda.seu-dominio.com.br
    DocumentRoot /var/www/agenda-cli/public

    <Directory /var/www/agenda-cli/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Em hospedagens com `public_html`, mantenha `src/`, `database/`, `routes/`, `vendor/` e `.env` fora da pasta pública sempre que o provedor permitir; apenas `public/` deve ficar exposta à web.

## Contas de demonstração

Depois do seed, use estas contas apenas no ambiente local/desenvolvimento:

| Perfil | E-mail | Senha |
| --- | --- | --- |
| Admin | `admin@agenda.local` | `Admin@123` |
| Dono | `dono@agenda.local` | `Dono@123` |
| Funcionário | `funcionario@agenda.local` | `Func@123` |
| Cliente | `cliente@agenda.local` | `Cliente@123` |

## Multi-tenancy

O tenant é o **estabelecimento**. Dados operacionais carregam `establishment_id`, e as consultas de painel sempre aplicam o estabelecimento do usuário autenticado. Admin é o único perfil com visão global.

Enquanto ainda não existe um seletor de estabelecimento no login do funcionário, o sistema impede que um mesmo perfil `employee` seja vinculado simultaneamente a estabelecimentos diferentes. Isso evita contexto de tenant ambíguo.

## Clientes e contas

O cliente operacional do estabelecimento é armazenado em `customers`. Ele pode existir sem uma conta de login, permitindo cadastrar clientes atendidos por telefone, balcão ou WhatsApp sem criar senha artificial.

Quando o cliente possui uma conta `client`, o registro de CRM pode ser vinculado por `user_id`. Reservas feitas pelo próprio cliente mantêm `client_user_id` e também ficam ligadas ao `customer_id`, fazendo o histórico público e o CRM convergirem.

## Regras de agenda

- Um profissional pode realizar mais de um serviço, mas nunca pode receber agendamentos sobrepostos.
- O horário semanal do estabelecimento define o funcionamento normal.
- Uma data especial sobrescreve o horário semanal apenas naquela data.
- Feriados nacionais ficam fechados por padrão e podem ser configurados para abrir em horário especial.
- Feriados estaduais, municipais e outras datas podem ser adicionados manualmente.
- Fechamentos emergenciais bloqueiam novos horários, sem cancelar automaticamente agendamentos já existentes.
- Cada profissional pode herdar o horário do estabelecimento, usar uma jornada própria ou marcar um dia da semana como folga.
- Bloqueios individuais retiram períodos específicos da disponibilidade do profissional.
- Reagendamentos recalculam a disponibilidade e continuam protegidos contra conflito simultâneo.
- Agendamentos manuais usam o mesmo motor de disponibilidade do fluxo público.
- Na reserva pública, “Primeiro horário disponível” pode selecionar automaticamente um profissional habilitado para o serviço.
- Cada estabelecimento pode definir antecedência mínima, janela máxima de reserva e intervalo entre atendimentos.
- O cliente pode cancelar online enquanto estiver dentro do prazo definido pelo estabelecimento.
- A lista de espera registra interesse sem bloquear a agenda e pode ser acompanhada por dono e funcionários.

## Operação

O estabelecimento possui telas para:

- agenda diária por profissional;
- criação manual de agendamentos;
- CRM de clientes e histórico de visitas;
- lista de espera;
- gestão da equipe;
- jornada individual e bloqueios de cada profissional;
- serviços e vínculos de profissionais;
- horários, feriados e exceções do estabelecimento;
- regras de antecedência, intervalo e cancelamento;
- confirmação, conclusão, falta, cancelamento e reagendamento;
- histórico de mudanças de cada agendamento.

## Próximos passos sugeridos

- Notificações por e-mail/WhatsApp e confirmação de presença.
- Automação da lista de espera quando surgir uma vaga.
- Pagamentos/sinal no agendamento.
- Dashboard de retenção, ticket médio, no-show e serviços mais vendidos.
- Refatoração de papéis por vínculo para suportar multiunidade de forma completa.
- Testes automatizados de unidade e integração.
