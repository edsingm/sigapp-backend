# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**SIGAPP** é uma plataforma SaaS multi-tenant para análise de viabilidade de terrenos e gestão imobiliária. Construída com Laravel 12, suporta prospecção de terrenos, estudos de viabilidade financeira (com geração de DRE), legalização, comitês de aprovação, negociações, contratos e um assistente de IA especializado em análise de terrenos.

---

## Commands

### Setup
```bash
composer setup          # Instala dependências, configura .env, gera key, migra e builda assets
```

### Desenvolvimento
```bash
composer dev            # Inicia servidor PHP, queue worker, logs e Vite em paralelo
php artisan serve       # Apenas o servidor PHP (porta 8000)
```

### Testes
```bash
composer test                                      # Suite completa (Feature + Unit)
php artisan test --filter=NomeDaClasse             # Teste específico por classe
php artisan test --filter=NomeDaClasse::nomeMetodo # Teste específico por método
php artisan test tests/Feature/                    # Apenas feature tests
php artisan test tests/Unit/                       # Apenas unit tests
```

> Os testes usam banco de dados SQLite em memória (`phpunit.xml`).

### Qualidade de Código
```bash
./vendor/bin/pint                 # Formata código (PSR-2)
./vendor/bin/pint --test          # Verifica formatação sem alterar
./vendor/bin/phpstan analyse app  # Análise estática (nível 8)
```

### Banco de Dados
```bash
# Central
php artisan migrate
php artisan migrate:fresh --seed

# Tenants
php artisan tenants:migrate
php artisan tenants:migrate --fresh
php artisan tenants:seed

# Seeders individuais
php artisan db:seed --class=PlanSeeder
php artisan db:seed --class=CentralAdminSeeder
```

### Comandos Artisan Customizados
```bash
php artisan bootstrap:central-admin     # Cria/atualiza admin central via env vars
php artisan sync-tenant-acl             # Sincroniza permissões/papéis por plano
php artisan apply-rbac-templates        # Aplica templates de permissões RBAC
php artisan cleanup:central-login-broker  # Remove tickets de login expirados
php artisan cleanup:pending-tenants     # Remove tenants pendentes após 24h
php artisan notify:overdue-legalizacoes # Notifica sobre etapas de legalização atrasadas
```

---

## Architecture

### Multi-Tenancy

A aplicação usa `stancl/tenancy` v3.8 com isolamento por banco de dados por tenant.

- **Domínio central**: `sigapp.com.br`, `localhost`, `127.0.0.1`
- **Domínios tenant**: `{tenant}.sigapp.com.br` (subdomínio)
- Rotas centrais estão em `routes/api.php`; rotas de tenant em `routes/tenant.php`
- Models centrais em `app/Models/Central/`; models de tenant em `app/Models/Tenant/`
- Migrations de tenant em `database/migrations/tenant/`

### Fluxo de Autenticação

**Login Broker (multi-tenant):**
1. Usuário faz login em `/api/v1/auth/login` no domínio central → recebe um **ticket**
2. Frontend redireciona para o subdomínio do tenant
3. Tenant chama `/api/v1/auth/exchange-ticket` para trocar o ticket por token Sanctum
4. Chamadas subsequentes usam `Authorization: Bearer {token}`

**Login Direto no Tenant:** `POST /api/v1/auth/login` no subdomínio do tenant → token Sanctum direto.

### Feature Gating por Plano

Planos: `broker`, `basico`, `master`. A matriz de features está em `config/plans.php` e na tabela `plan_role_permission_templates`.

- Middleware `check.feature:nome_feature` bloqueia se a feature não está no plano
- Middleware `enforce.limits:terrenos,users` verifica cotas de uso
- Sincronização via `SyncTenantAclCommand` e `ApplyRbacTemplatesCommand`

### Padrão de Camadas

```
Controller (HTTP only) → Service (lógica de negócio) → Repository (acesso a dados)
```

### Módulos do Domínio

| Módulo | Feature Gate | Descrição |
|--------|-------------|-----------|
| Prospection | `prospection` | Terrenos, workflow de prospecção |
| Viabilidade | `viabilities.enabled` | Estudos financeiros, DRE, aprovações |
| Legalização | `legalization` | Etapas de regularização fundiária |
| Comitê | `committee` | Reuniões e decisões de aprovação |
| Negociação | `negotiation` | Processo de venda e contratos |
| Projetos | `projects_room` | Sala de projetos por deal |
| IA | `ai` | Assistente SIG_IA com ferramentas |

### Assistente de IA (SIG_IA)

- **Localização**: `app/Ai/Agents/SIG_IA.php`
- Provider: OpenRouter (configurável via `OPENROUTER_API_KEY` e `AI_OPENROUTER_AGENT_MODEL`)
- Usa `RemembersConversations` trait para persistência no banco
- **3 ferramentas**: `ListTerrenosTool`, `GetTerrenoDetailsTool`, `GetViabilidadesTool`
- Prompts e respostas exclusivamente em português (BR)

### Autorização

- Biblioteca: `spatie/laravel-permission`
- Middleware customizado: `permission.gate:modulo,acao`
- Papéis de tenant admin requerem middleware `tenant.admin`
- Papéis centrais requerem middleware `user.admin`

### Notificações

- Email via Resend (`resend/resend-laravel`)
- Push mobile via `MobileDeviceInstallation` e `MobileNotification`

---

## Key Configuration Files

| Arquivo | Propósito |
|---------|-----------|
| `config/plans.php` | Matriz de features e limites por plano |
| `config/tenancy.php` | Configuração multi-tenant (domínios, bootstrappers) |
| `config/ai.php` | Provedores de IA (OpenRouter, OpenAI, Gemini, Anthropic) |
| `config/cashier.php` | Stripe (moeda BRL, mapeamento de planos) |
| `config/permission.php` | Tabelas e modelos do Spatie Permission |

---

## Environment Variables (Key)

```env
APP_DOMAIN=sigapp.com.br
CENTRAL_DOMAINS=localhost,127.0.0.1,sigapp.com.br
TENANCY_DATABASE_PREFIX=tenant_

# Auth
SANCTUM_STATEFUL_DOMAINS=localhost:8080,*.sigapp.test
CORS_ALLOWED_ORIGINS=http://localhost:8080

# AI
AI_PROVIDER=openrouter
OPENROUTER_API_KEY=...
AI_OPENROUTER_AGENT_MODEL=z-ai/glm-4.5-air:free

# Stripe
STRIPE_KEY=...
STRIPE_SECRET=...
STRIPE_WEBHOOK_SECRET=...
CASHIER_CURRENCY=brl

# Central Admin Bootstrap
CENTRAL_ADMIN_NAME=...
CENTRAL_ADMIN_EMAIL=...
CENTRAL_ADMIN_PASSWORD=...
```

---

## Technology Stack

- **PHP 8.2+**, Laravel 12, PHPUnit 11
- **Autenticação**: Laravel Sanctum (token-based)
- **Multi-tenancy**: stancl/tenancy v3.8
- **ACL**: spatie/laravel-permission v6
- **Billing**: Laravel Cashier (Stripe)
- **AI**: laravel/ai SDK
- **PDF**: spatie/laravel-pdf
- **Excel**: maatwebsite/excel
- **Email**: Resend
- **Formatação**: Laravel Pint (PSR-2)
- **Análise Estática**: PHPStan nível 8
- **Cache/Queue**: Redis
