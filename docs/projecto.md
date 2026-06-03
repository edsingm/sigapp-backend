# Estrutura do Projeto SIGAPP

## Visão Geral

SIGAPP é uma plataforma SaaS multi-tenant voltada para prospecção, análise de viabilidade de terrenos e gestão imobiliária.

**Stack:** Laravel 13 + PHP 8.2+, PostgreSQL, Redis, Sanctum, Stancl/Tenancy, Spatie/Permission, Stripe (Cashier), Scramble (API Docs), Resend, OpenRouter (IA), Spatie Browsershot/PDF, Maatwebsite Excel

---

## Estrutura de Diretórios

```
sigapp/
├── database/                    # Banco de dados externo (infra)
│   ├── docker-compose.yml       # PostgreSQL standalone
│   └── data/                    # Volume persistente do PG
│
├── backend/                     # API Laravel (este repositório)
│   ├── app/
│   │   ├── Models/
│   │   │   ├── Central/         # Models do domínio central (User, Tenant, Plan, etc.)
│   │   │   └── Tenant/          # Models do domínio tenant (Terreno, Produto, Viabilidade, etc.)
│   │   ├── Services/            # Serviços de negócio (central + tenant)
│   │   │   ├── Tenant/          # Serviços específicos por tenant
│   │   │   │   ├── Viabilidade/ # Motor de cálculo financeiro
│   │   │   │   ├── Legalizacao/ # Gestão de legalização
│   │   │   │   └── ...
│   │   │   ├── Ai/              # Serviços de IA (scoring, embeddings, insights, etc.)
│   │   │   └── ...
│   │   ├── Repositories/        # Acesso ao banco (com Contracts/)
│   │   │   ├── Tenant/          # Repositories de tenant
│   │   │   └── Contracts/       # Interfaces dos repositories
│   │   ├── Ai/Agents/           # Agentes de IA (SIG_IA)
│   │   │   └── Tools/           # Tools/Functions para o agente
│   │   ├── Enums/               # PHP Enums tipados
│   │   ├── Http/Controllers/    # Controllers (HTTP apenas)
│   │   │   └── Api/V1/          # Controllers versionados
│   │   ├── Http/Requests/       # FormRequests (validação + autorização)
│   │   ├── Http/Resources/      # API Resources
│   │   ├── Http/Middleware/     # Middlewares customizados
│   │   ├── Policies/            # Policies de autorização
│   │   └── ...
│   ├── config/
│   │   ├── tenancy.php          # Configuração multi-tenant
│   │   ├── plans.php            # Matriz de features/limites por plano
│   │   ├── entitlements.php     # Sistema granular de entitlements
│   │   ├── viabilidade.php      # Defaults de parâmetros de viabilidade
│   │   ├── ai.php               # Provedores de IA
│   │   └── permission.php       # Spatie Permission
│   ├── database/
│   │   ├── migrations/          # Migrations centrais
│   │   ├── migrations/tenant/   # Migrations de tenant
│   │   ├── seeders/             # Seeders
│   │   └── factories/           # Factories de testes
│   ├── routes/
│   │   ├── api.php              # Rotas centrais (auth, admin, webhook, blog)
│   │   └── tenant.php           # Rotas de tenant (viabilidade, terrenos, etc.)
│   ├── tests/
│   │   ├── Feature/             # Testes de integração
│   │   └── Unit/                # Testes unitários
│   ├── docker-compose.yml       # Composição: Laravel + Redis
│   ├── composer.json
│   └── phpunit.xml
│
├── frontend/                    # Frontend (Hono/React)
│   └── docker-compose.yml       # Frontend Docker
│
└── database/README.md
```

---

## Infraestrutura & Docker

O sistema roda em 3 composições Docker separadas:

### 1. Database (`database/docker-compose.yml`)

| Serviço     | Container    | Descrição               |
| ------------ | ------------ | ------------------------- |
| `database` | `database` | PostgreSQL 17, porta 5432 |

- Rede `sigapp` (bridge)
- Volume persistente em `database/data`
- Credenciais: `root` / `123456` / banco `sigapp`

### 2. Backend (`backend/docker-compose.yml`)

| Serviço  | Container          | Descrição                   |
| --------- | ------------------ | ----------------------------- |
| `back`  | `sigapp-backend` | Laravel + PHP-FPM, porta 8000 |
| `redis` | `sigapp-redis`   | Redis 7, porta 6379           |

- Conecta à rede `database_sigapp` (externa, criada pelo compose do database)
- Volume bind-mount de `.` para `/var/www` (development)
- Variáveis conectam ao serviço `database` da outra compose

### 3. Frontend (`frontend/docker-compose.yml`)

| Serviço  | Descrição                       |
| --------- | --------------------------------- |
| `front` | Aplicação frontend (Hono/React) |

---

## Multi-Tenancy

### Modelo de Isolamento

- **Database-per-tenant**: cada tenant tem seu próprio banco PostgreSQL com prefixo `tenant_`
- O tenant é identificado pelo subdomínio: `{tenant}.sigapp.com.br`
- Central opera em `sigapp.com.br`, `localhost`, `127.0.0.1`

### Bootstrap e Isolamento

- `stancl/tenancy` intercepta o request pelo hostname → resolve o Tenant model → troca a conexão de banco
- Migrations centrais: `database/migrations/`
- Migrations de tenant: `database/migrations/tenant/`
- Rota central: `routes/api.php` — Rota tenant: `routes/tenant.php`

### Planos e Feature Gating

| Plano      | Uso                                         |
| ---------- | ------------------------------------------- |
| `broker` | Prospecção básica                        |
| `basico` | Prospecção + viabilidade                  |
| `master` | Tudo + comitê, legalização, projetos, IA |

Features são checadas via middleware `CheckFeature`. O sistema de entitlements é mais granular e gerenciado por `EntitlementService` e `TenantPlanService`. A matriz completa está em `config/plans.php` e o sistema de entitlements em `database/seeders/EntitlementSeeder.php`.

---

## Caminhos de Desenvolvimento

### Rodar comandos no container

```bash
docker compose exec back php artisan ...
```

### Rodar testes

```bash
# Todos os testes
docker compose exec back php artisan test

# Testes específicos
docker compose exec back php artisan test --filter=Viabilidade
docker compose exec back php artisan test tests/Unit/Services/Viabilidade/
docker compose exec back php artisan test --filter=nome_do_metodo
```

Os testes unitários usam SQLite em memória. Testes de Feature chamam as rotas HTTP reais.

### Migrations

```bash
# Central
docker compose exec back php artisan migrate
docker compose exec back php artisan migrate:fresh --seed

# Tenants (aplica migrations em todos os bancos de tenant)
docker compose exec back php artisan tenants:migrate
docker compose exec back php artisan tenants:migrate --fresh
docker compose exec back php artisan tenants:seed
```

### Qualidade de código

```bash
docker compose exec back ./vendor/bin/pint           # Formatar
docker compose exec back ./vendor/bin/phpstan analyse app  # Análise estática
```

---

## Módulos do Domínio

#### Terrenos (Prospection)

Gestão de terrenos, topografia, documentação, workflow de prospecção, corretores externos, regionais.

#### Workflow

Sistema de workflow com versões, aprovações e histórico de status para terrenos.

#### Viabilidade

Motor financeiro que calcula fluxo de caixa, DRE e indicadores (TIR, ROI, VPL, payback) para projetos imobiliários. As curvas de vendas e obra vêm diretamente da tabela `produtos` como arrays JSON. Documentação detalhada em `docs/calculo_viabilidade.md`.

#### Legalização

Gestão de etapas de regularização fundiária com dependências, custos e prazos.

#### Projetos

Gestão de projetos imobiliários com aprovações.

#### Comitê

Reuniões e decisões de aprovação para projetos.

#### IA (SIG_IA)

Assistente de IA que responde perguntas sobre terrenos e viabilidades usando múltiplas tools. Provider via OpenRouter (Laravel AI SDK). Inclui:

- Agent conversations com histórico
- AI scoring e embeddings para documentos
- Predictive analysis
- Anomaly detection
- Workflow automation via AI tools

#### Mobile

Notificações push mobile e instalações de dispositivos.

#### Billing (Stripe)

Gestão de planos, subscriptions e pagamentos via Stripe Cashier.

#### Blog/Posts

Sistema de posts central para landing page/marketing.

---

## Testes de Viabilidade

Localização e status dos testes de viabilidade:

| Arquivo                                                                 | Tipo    | Qtd       |
| ----------------------------------------------------------------------- | ------- | --------- |
| `tests/Unit/Services/Viabilidade/ViabilidadeUnificadoServiceTest.php` | Unit    | 25 testes |
| `tests/Feature/Tenant/ViabilidadeRealOutputTest.php`                  | Feature | 3 testes  |

Todos passando (28 testes, 94 assertions).

Comando para rodar apenas os testes de viabilidade:

```bash
docker compose exec back php artisan test --filter=Viabilidade
```

**Total de testes no projeto:** 89 arquivos de teste.

---

**Última atualização:** 03 de Junho de 2026
