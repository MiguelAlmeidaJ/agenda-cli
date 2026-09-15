# Agenda CLI

Sistema web multi-tenant de agendamentos feito em PHP + MySQL, com interface em Bootstrap.

## Perfis

- **Admin**: visão global da plataforma.
- **Dono do estabelecimento**: gerencia serviços, horários e agenda do estabelecimento.
- **Funcionário**: vinculado a um estabelecimento e preparado para receber serviços/agendamentos.
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

Para instalações que já possuem o banco criado, aplique as migrations novas da pasta `database/migrations/`.

A gestão de feriados, fechamentos e horários excepcionais requer:

```bash
mysql -u usuario -p banco < database/migrations/2026_09_15_create_special_hours.sql
```

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

## Regras de agenda

- Um profissional pode realizar mais de um serviço, mas nunca pode receber agendamentos sobrepostos.
- O horário semanal define o funcionamento normal.
- Uma data especial sobrescreve o horário semanal apenas naquela data.
- Feriados nacionais ficam fechados por padrão e podem ser configurados para abrir em horário especial.
- Feriados estaduais, municipais e outras datas podem ser adicionados manualmente.
- Fechamentos emergenciais bloqueiam novos horários, sem cancelar automaticamente agendamentos já existentes.

## Próximos passos sugeridos

- CRUD completo de estabelecimentos pelo admin.
- Convites e gestão de funcionários pelo dono.
- Horários individuais por funcionário e folgas recorrentes.
- Notificações por e-mail/WhatsApp.
- Pagamentos e política de cancelamento.
- Testes automatizados de unidade e integração.
