# Agenda CLI

Sistema web multi-tenant de agendamentos feito em PHP + MySQL, com interface em Bootstrap.

## Perfis

- **Admin**: visão global da plataforma.
- **Dono do estabelecimento**: gerencia serviços, horários e agenda do estabelecimento.
- **Funcionário**: vinculado a um estabelecimento e preparado para receber serviços/agendamentos.
- **Cliente**: navega pelos estabelecimentos e agenda horários.

## Stack

- PHP 8.3
- MySQL 8.4
- PDO
- Composer / PSR-4
- Bootstrap 5.3 + Bootstrap Icons
- Apache com `mod_rewrite`

## Como executar

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php database/seed.php
```

Acesse `http://localhost:8080`.

## Multi-tenancy

O tenant é o **estabelecimento**. Dados operacionais carregam `establishment_id`, e as consultas de painel sempre aplicam o estabelecimento do usuário autenticado. Admin é o único perfil com visão global.

## Próximos passos sugeridos

- CRUD completo de estabelecimentos pelo admin.
- Convites e gestão de funcionários pelo dono.
- Horários individuais por funcionário e folgas recorrentes.
- Notificações por e-mail/WhatsApp.
- Pagamentos e política de cancelamento.
- Testes automatizados e pipeline CI.
