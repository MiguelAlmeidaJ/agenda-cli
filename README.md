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
- Extensões `pdo_mysql`, `curl` e `fileinfo`
- MySQL 8 ou superior
- Composer 2
- Servidor web apontando o DocumentRoot para a pasta `public/`
- No Apache, `mod_rewrite` habilitado e `AllowOverride All` para o `.htaccess`

## Instalação

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

Para dados de demonstração em desenvolvimento:

```bash
php database/seed.php
```

> Em produção, não execute o seed de demonstração.

## Atualizações de banco

Para instalações existentes, aplique as migrations na ordem em que foram adicionadas.

```bash
mysql -u usuario -p banco < database/migrations/2026_09_15_create_special_hours.sql
mysql -u usuario -p banco < database/migrations/2026_09_15_operations_v2.sql
mysql -u usuario -p banco < database/migrations/2026_09_15_customers_and_manual_booking.sql
mysql -u usuario -p banco < database/migrations/2026_09_15_booking_rules_and_waitlist.sql
mysql -u usuario -p banco < database/migrations/2026_09_15_management_notifications.sql
mysql -u usuario -p banco < database/migrations/2026_09_15_profile_details.sql
mysql -u usuario -p banco < database/migrations/2026_09_15_cloudinary_media.sql
mysql -u usuario -p banco < database/migrations/2026_09_15_establishment_location.sql
mysql -u usuario -p banco < database/migrations/2026_09_21_security_hardening.sql
mysql -u usuario -p banco < database/migrations/2026_09_21_client_experience.sql
```

As migrations mais recentes adicionam perfil detalhado, mídia no Cloudinary, localização geocodificada, proteção contra tentativas repetidas de login e o ciclo de auto-reagendamento/lista de espera do cliente.

## Automação por cron

A rotina `bin/cron.php` procura vagas para a lista de espera e prepara confirmações, cancelamentos e lembretes configurados na central de notificações.

Em um servidor Linux, uma configuração típica é executar a cada 5 minutos:

```cron
*/5 * * * * /usr/bin/php /caminho/agenda-cli/bin/cron.php >> /caminho/agenda-cli/storage-cron.log 2>&1
```

Ajuste o caminho do PHP e do projeto conforme a hospedagem. O cron não envia mensagens diretamente para um provedor externo nesta etapa; ele prepara a caixa de saída e deduplica os eventos.

## Preparação para WhatsApp

A tela **Notificações** permite ativar os eventos desejados e trabalhar inicialmente em modo manual. Cada mensagem pendente pode ser aberta no WhatsApp com o texto já preenchido e depois marcada como enviada.

A arquitetura deixa as credenciais do futuro gateway fora do banco e do Git. A próxima integração pode usar Meta Cloud API ou outro gateway sem mudar CRM, agenda, lista de espera ou regras de negócio.

## Apache

Configure o VirtualHost para apontar para `public/`:

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

Em hospedagens com `public_html`, mantenha `src/`, `database/`, `routes/`, `vendor/` e `.env` fora da pasta pública sempre que o provedor permitir.

## Contas de demonstração

| Perfil | E-mail | Senha |
| --- | --- | --- |
| Admin | `admin@agenda.local` | `Admin@123` |
| Dono | `dono@agenda.local` | `Dono@123` |
| Funcionário | `funcionario@agenda.local` | `Func@123` |
| Cliente | `cliente@agenda.local` | `Cliente@123` |

## Multi-tenancy

O tenant é o **estabelecimento**. Dados operacionais carregam `establishment_id`, e as consultas de painel aplicam o estabelecimento do usuário autenticado. Admin é o único perfil com visão global.

Enquanto não existe seletor de estabelecimento no login do funcionário, o sistema impede vínculo simultâneo de um mesmo perfil `employee` com estabelecimentos diferentes.

## Clientes e contas

O cliente operacional é armazenado em `customers` e pode existir sem conta de login. Quando há uma conta `client`, o CRM pode ser vinculado por `user_id`, unificando histórico público e atendimento manual.

## Regras de agenda

- Um profissional pode realizar vários serviços, mas nunca recebe agendamentos sobrepostos.
- Horário semanal, datas especiais e feriados definem o funcionamento do estabelecimento.
- Cada profissional pode herdar a jornada do estabelecimento, usar jornada própria ou ter folga.
- Bloqueios individuais retiram períodos específicos da disponibilidade.
- Reagendamentos recalculam disponibilidade e continuam protegidos contra conflito simultâneo.
- Agendamento manual usa o mesmo motor de disponibilidade do fluxo público.
- “Primeiro horário disponível” resolve um profissional real antes da confirmação.
- Cada estabelecimento define antecedência mínima, janela futura, intervalo entre atendimentos e prazo de cancelamento.
- Lista de espera não bloqueia agenda e é cruzada automaticamente com vagas disponíveis.

## Operação

O estabelecimento possui telas para agenda diária, agendamento manual, CRM, lista de espera, equipe, serviços, horários, regras de reserva, notificações e indicadores gerenciais.

O dashboard de indicadores acompanha faturamento concluído, evolução mensal, atendimentos, novos clientes, falta, cancelamento, lista de espera, serviços e profissionais com maior movimento.

## Próximos passos sugeridos

- Adaptador real de WhatsApp para o provedor escolhido.
- Confirmação de presença por link/resposta.
- Pagamentos e sinal no agendamento.
- Indicadores de retenção, ticket médio e recorrência de clientes.
- Refatoração de papéis por vínculo para suportar multiunidade completa.
- Ampliar os testes automatizados para cobrir disponibilidade, isolamento de tenant, lista de espera e fluxos de agendamento.
