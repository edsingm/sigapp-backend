# AGENTS.md — Backend SIGAPP (Laravel 13 · Multi-Tenant)

Este arquivo contém as regras obrigatórias que todas as IAs (Cursor, Claude, Copilot, Gemini, etc.) devem seguir ao trabalhar neste projeto. Ele descreve **o que o repositório realmente é** — não um template genérico. Em caso de dúvida, o código e os testes de arquitetura (`tests/Architecture/`) são a fonte da verdade.

> **Nota sobre convenção vs. regra oficial:** o Laravel não impõe arquitetura em camadas (Controller → Service → Repository). As regras deste documento que vão além do padrão do framework são **convenções deste projeto**, adotadas para testabilidade e desacoplamento — e várias delas são **verificadas automaticamente** pelos testes em `tests/Architecture/`.

## 📝 Manutenção obrigatória deste arquivo

- **Sempre leia este `AGENTS.md` antes de alterar o backend.**
- Ao implementar **feature nova** ou **alteração considerável** que mude arquitetura, módulos, rotas, middlewares, jobs/scheduler, billing, IA, storage/uploads, permissões/RBAC, variáveis de ambiente, comandos, deploy ou regras de teste, atualize este `AGENTS.md` no mesmo conjunto de mudanças.
- Não atualize este arquivo para microfixes sem impacto estrutural. Se a mudança exigir que a próxima IA saiba de uma nova regra, fluxo ou superfície do sistema, documente aqui.
- A atualização deve ser cirúrgica: ajuste a seção existente, mantenha o texto fiel ao código real e não transforme o documento em changelog.

---

## 🎯 Visão Geral do Projeto

**SIGAPP** é um SaaS multi-tenant de gestão imobiliária para incorporadoras/loteadoras: prospecção e qualificação de **terrenos**, estudo de **viabilidade** econômica (DRE, fluxo mensal, indicadores), **comitê** de aprovação, **negociação**, **contratos**, **legalização** (etapas/checklist), **projetos**, dashboards e um **agente de IA** (SIG_IA) com dezenas de tools. Cada cliente (tenant) acessa via subdomínio (`{tenant}.sigapp.com.br`) e o painel administrativo central roda nos domínios centrais.

| Item | Valor |
|---|---|
| **Framework** | Laravel 13 (`laravel/framework ^13.0`) |
| **Linguagem** | PHP **8.4+** (`php ^8.4`, PHPStan `phpVersion: 80400`) |
| **Banco de dados** | PostgreSQL (central + 1 schema por tenant, com `pgvector` para embeddings). SQLite `:memory:` nos testes |
| **Storage** | Laravel Storage local/S3 (`league/flysystem-aws-s3-v3`); documentos e relatórios PDF de IA usam o disk `s3`; uploads contam limite `storage_gb` do plano |
| **Multi-tenancy** | `stancl/tenancy ^3.8` — manager customizado `PostgreSQLSchemaPublicManager`, identificação por subdomínio + header `X-Tenant` (fallback local/testing) |
| **Autenticação** | Laravel Sanctum (tokens Bearer) + broker de login central com transfer tickets |
| **Autorização/RBAC** | `spatie/laravel-permission ^7.0` (`teams => false`) + templates de permissão por plano |
| **Billing** | Laravel Cashier (Stripe) `^16.0` — planos, entitlements, cupons, dunning, webhooks |
| **IA** | **Laravel AI SDK** (`laravel/ai ^0.7`) — agente `SIG_IA`, providers DeepSeek/Gemini/OpenRouter via `config/ai.php` |
| **E-mail** | Resend (`resend/resend-laravel`) via Notifications |
| **PDF** | `spatie/laravel-pdf` + `spatie/browsershot` (Chromium — `BROWSERSHOT_CHROME_PATH`) |
| **Excel** | `maatwebsite/excel ^3.1` (`app/Exports/`) |
| **Docs da API** | `dedoc/scramble` — UI em `/docs/api` (alias `/docs`) |
| **Testes** | PHPUnit 13 (suites `Architecture`, `Unit`, `Feature`) — **não** usa Pest |
| **Formatação** | Laravel Pint, preset `laravel` (`pint.json`) |
| **Análise estática** | PHPStan **nível 8** + bleedingEdge + baseline (`phpstan.baseline.neon`) |
| **Dev local** | Laravel Herd (macOS) ou `composer dev` / Docker (`.docker/` + `docker-compose.yml` — ver seção Docker) |
| **Frontend** | Next.js separado (repositório irmão) — CORS via `CORS_ALLOWED_ORIGINS`, URLs em `FRONTEND_URL`/`LANDING_URL` |

### Comandos essenciais

```bash
composer setup                      # install + .env + key + migrate
composer dev                        # serve + queue:listen + pail + vite (concurrently)
composer test                       # config:clear + php artisan test
composer analyse                    # phpstan (memory 512M)
./vendor/bin/pint --test            # checa formatação (sem alterar)
php artisan test --testsuite=Architecture   # só os testes de arquitetura
```

---

## 🐳 Docker e Ambientes

Há duas formas de rodar localmente: **Herd/`composer dev`** (nativo, macOS) ou **Docker**. A infra Docker vive em `.docker/` (diretório oculto) + `docker-compose.yml` (dev) + `docker-compose.prod.yml` (prod).

### Imagem (`.docker/Dockerfile`, multi-stage)

| Stage | Conteúdo |
|---|---|
| `base` | `php:8.4-fpm` + extensões (`pdo_pgsql`, `redis` via PECL, `gd`, `intl`, `zip`, `bcmath`, `pcntl`, `exif`, `mbstring`) + **Node 20 + Chromium + Puppeteer** (necessários para Browsershot/`spatie/laravel-pdf`) + Composer |
| `dev` | código via **bind mount** (`.:/var/www`); entrypoint (`entrypoint.dev.sh`) instala `vendor/` se faltar, garante `.env`/`APP_KEY`, roda `optimize:clear` e sobe `php artisan serve` na porta **8000** |
| `prod` | código **embutido na imagem** (`composer install --no-dev` otimizado) + **nginx + php-fpm + supervisord** |

### Compose

- **Dev (`docker-compose.yml`)**: services `back` (`sigapp-backend:1.0-dev`, porta 8000) e `redis` (`redis:7-alpine`). O **PostgreSQL não está no compose** — é um container/host externo chamado `database`, alcançado pela rede externa `database_sigapp` (precisa existir: `docker network create database_sigapp`). As variáveis de ambiente de dev (DB, Redis, CORS, Sanctum, `CENTRAL_DOMAINS=localhost,127.0.0.1,sigapp-backend`, Chromium) já vêm definidas no compose.
- **Prod (`docker-compose.prod.yml`)**: target `prod`, porta interna `80` via `expose` (sem publicação no host), PostgreSQL/Redis externos gerenciados pelo Coolify, envs obrigatórios via `${VAR:?}` e healthcheck em `GET /api/v1/health`.

### Produção — quem roda o quê

- `entrypoint.prod.sh` prepara caches e sobe o supervisord; ele **não executa migrations** durante restart/scale.
- Primeiro deploy em banco vazio: execute `/usr/local/bin/sigapp-bootstrap` uma única vez (`migrate` + `db:seed`). Releases seguintes executam `/usr/local/bin/sigapp-release` (`migrate` central + `tenants:migrate`) antes de trocar o tráfego.
- `supervisord.conf` mantém 4 processos: **nginx**, **php-fpm**, **`queue:work --queue=tenant-provisioning,default --sleep=3 --tries=3 --max-time=3600`** e **`schedule:work`**. Ou seja: **fila e scheduler em produção rodam via supervisord** — não há cron; jobs de provisionamento de tenants usam a fila prioritária `tenant-provisioning`; um scheduled command novo só precisa estar em `routes/console.php`.
- `nginx.conf`: root em `public/`, `client_max_body_size 50M` (limite de upload), `fastcgi_read_timeout 120s` (teto para requests longos — PDFs/exports pesados devem ir para Jobs).

### Implicações para quem altera o código

- Dependência nova de **sistema** (extensão PHP, binário, fonte) → editar `.docker/Dockerfile` (e lembrar que o stage `base` serve dev e prod).
- Dependência/driver novo de **storage externo** (ex.: S3, MinIO) → atualizar `composer.json` se necessário, `config/filesystems.php`, `.env.example`, compose/deploy e esta seção.
- Migrations de produção rodam explicitamente pelo script de release, nunca implicitamente no startup. Todo `down()` continua obrigatório e migrations aplicadas nunca devem ser editadas.
- `route:cache`/`config:cache` rodam no deploy — não use closures em rotas de `routes/api.php`/`tenant.php` que quebrem o cache de rotas fora dos padrões já existentes, nem `env()` fora de `config/`.
- O `.dockerignore` exclui `.env*` (exceto `.env.example`) — configuração de prod entra **somente** por variável de ambiente do compose.
- O healthcheck de prod depende de `GET /api/v1/health` (definido em `routes/api.php`) — não remova nem proteja essa rota com auth/throttle agressivo.

---

## 🏗️ Multi-Tenancy — conceito central do projeto

Tudo neste backend é dividido em **dois contextos**. Antes de tocar em qualquer arquivo, identifique em qual contexto ele vive:

| | **Central** | **Tenant** |
|---|---|---|
| Rotas | `routes/api.php` (restringidas aos `central_domains` via `Route::domain()`) | `routes/tenant.php` (registrado pelo `TenancyServiceProvider::mapRoutes()` com o grupo de middleware `tenant`) |
| Models | `app/Models/Central/` (+ `App\Models\User`, `AuditLog`, `ConsentLog`) | `app/Models/Tenant/` |
| Controllers | `app/Http/Controllers/Api/V1/` e `Api/V1/Admin/` | `app/Http/Controllers/Api/V1/Tenant/` (+ `Tenant/Admin/`) |
| Banco | Conexão central (PostgreSQL) | 1 **schema** PostgreSQL por tenant (`tenant_{slug}` — prefixo `TENANCY_DATABASE_PREFIX`) |
| Migrations | `database/migrations/` | `database/migrations/tenant/` |
| Usuário | `App\Models\User` (admins da plataforma, `UserType::SIGAPP`) | `App\Models\Tenant\User` (`UserType::TENANT`) |
| Exemplos | planos, entitlements, cupons, tenants, blog, signup, webhooks Stripe | terrenos, viabilidades, comitê, contratos, legalização, projetos, IA |

Regras:

- **Identificação do tenant**: subdomínio em produção; header `X-Tenant` apenas em `local`/`testing`/`development` (middleware `App\Http\Middleware\InitializeTenancyFlexible`).
- Os middlewares `central.context` (`EnsureCentralContext`) e `tenant.context` (`EnsureTenantContext`) garantem que a rota roda no contexto certo — **toda rota nova deve declarar um deles**.
- `auth.central` / `auth.tenant` garantem que o usuário autenticado pertence ao contexto (guard Sanctum é compartilhado).
- Nunca referencie um model `Central` dentro de código tenant (e vice-versa) sem necessidade explícita — quando precisar (ex.: `Tenant`, `Plan`), acesse via serviço/`tenancy()`.
- O manager de banco é o customizado `App\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaPublicManager` (schemas, não bancos separados). O identificador vem de `Tenant::makeTenantDatabaseIdentifier($slug)` (ver `TenancyServiceProvider::register()`).
- Cache, Redis e storage local são prefixados/sufixados por tenant (ver `config/tenancy.php`). Documentos e relatórios PDF de IA usam `Storage::disk('s3')`; ao mexer nesses fluxos, preserve isolamento por tenant e mantenha métricas/limites do plano.
- Ciclo de vida do tenant: signup público (`SignupController` → `TenantSignupService` → `CreateFullTenantJob`), limpeza de pendentes (`tenants:cleanup-pending`), ativação/suspensão via admin central (`TenantStatus`).
- Scripts auxiliares de operação em `scripts/pgsql/` (criação de schemas, descoberta de tenants, reset de sequences, validação de contagens).

---

## 🚨 REGRAS OBRIGATÓRIAS

### 1. PHP e Padrões de Código

- PHP mínimo: **8.4** — use os recursos modernos da linguagem.
- Seguir **PSR-12** (estilo) e **PSR-4** (autoload). A formatação é aplicada via **Laravel Pint** (`./vendor/bin/pint`) — nunca formate manualmente nem discuta estilo em review; o Pint é a fonte da verdade.
- **Sempre declare tipos** em propriedades, parâmetros e retornos — nunca omita.
- Use **enums** nativos (ver `app/Enums/`) ao invés de constantes mágicas ou strings avulsas. Enums compartilhados entre central e tenant ficam em `app/Enums/Common/`.
- Use **readonly properties** e **constructor promotion** onde aplicável.
- Nunca use `mixed` quando um tipo preciso é possível.
- Nunca use `@phpstan-ignore` sem comentário explicativo; prefira corrigir o tipo.
- Novos arquivos de domínio devem usar `declare(strict_types=1);` (padrão dos arquivos mais recentes, ex.: `DomainException`).

### 2. Arquitetura: Controller → Service → Repository

> ⚠️ Convenção deste projeto, **verificada por testes**: `tests/Architecture/LayerBoundariesTest.php`, `ServicesArchitectureTest.php`, `AdminControllerArchitectureTest.php`, `PublicControllerArchitectureTest.php`, `ModulesControllerArchitectureTest.php`. Se a sua mudança quebrar um desses testes, corrija a arquitetura — não o teste.

| Camada | Responsabilidade | Onde |
|---|---|---|
| **Controller** | Recebe HTTP, delega ao Service, retorna Resource/`ApiResponseService` | `app/Http/Controllers/Api/V1/...` |
| **Service** | Lógica de negócio, orquestração, eventos | `app/Services/` (por domínio: `Tenant/`, `Billing/`, `Auth/`, `Ai/`, `Admin/`, `Acl/`, ...) |
| **Repository** | Único lugar com queries Eloquent | `app/Repositories/` (+ `Repositories/Tenant/`) |
| **Contract** | Interface do repository, quando precisa de mock/troca | `app/Repositories/Contracts/` — bind no `AppServiceProvider::register()` |
| **Model** | Entidade: relações, casts, scopes, accessors | `app/Models/Central/` e `app/Models/Tenant/` |
| **FormRequest** | Validação + autorização de entrada | `app/Http/Requests/` (espelha a estrutura dos controllers) |
| **Resource** | Formatação de saída | `app/Http/Resources/` |

Regras de camada (as que os testes de arquitetura verificam estão marcadas ✅):

- **Controllers são thin**: sem lógica de negócio, sem `->validate()` inline ✅, sem queries Eloquent diretas (`Model::query()`, `::create()`, `findOrFail()`) ✅.
- **Services não dependem de `Illuminate\Http\Request`** ✅ — recebem arrays validados, DTOs (`app/DTOs/`) ou models. Services que orquestram não fazem query direta ✅ (lista controlada em `ServicesArchitectureTest`).
- **Repositories são o único lugar** onde o Eloquent é consultado diretamente.
- Novo repository com interface? Registre o bind em `AppServiceProvider::register()` (siga o bloco existente de ~40 binds).
- Não crie Repository para query trivial de uso único — abstração especulativa. Crie quando a consulta é reutilizada ou precisa ser mockada.
- Models não são anêmicos: scopes, casts, accessors e métodos que descrevem o próprio dado pertencem ao Model.
- Side-effects (e-mail, push, histórico, timeline) saem via **Events + Listeners** (`app/Events/Tenant/`, `app/Listeners/Tenant/`, registrados no `EventServiceProvider`) — ver o fluxo de workflow (`WorkflowTransitioned` → notificações, histórico, atividade).

### 3. Estrutura de Pastas (real)

```
app/
  Console/Commands/       → comandos Artisan (RBAC, tenants, digests, IA, limpeza)
  DTOs/                   → Data Transfer Objects (ex.: RequestContext)
  Enums/                  → enums de domínio; Common/ para os compartilhados
  Events/Tenant/          → eventos de domínio do tenant
  Exceptions/             → exceções de domínio (base: DomainException)
  Exports/                → exports Excel (maatwebsite/excel)
  Http/
    Controllers/Api/V1/           → central (público, auth, blog, signup, planos, webhook)
    Controllers/Api/V1/Admin/     → painel admin central
    Controllers/Api/V1/Tenant/    → app do tenant (+ Tenant/Admin/ e Tenant/Common/)
    Middleware/                   → ver aliases em bootstrap/app.php
    Requests/                     → FormRequests (Admin/, Tenant/, Tenant/Admin/)
    Resources/                    → API Resources (Admin/, Tenant/, ...)
  Jobs/                   → jobs assíncronos (todos com failed() — teste exige)
  Listeners/Tenant/       → handlers dos eventos de domínio
  Models/Central/         → models da conexão central
  Models/Tenant/          → models do schema do tenant
  Notifications/          → notificações (Resend); Workflow/ para as de fluxo
  Observers/Tenant/       → observers (ex.: TerrenoObserver)
  Policies/Tenant/        → policies
  Providers/              → AppServiceProvider (binds), EventServiceProvider, TenancyServiceProvider
  Repositories/           → repositories (+ Contracts/ e Tenant/)
  Services/               → serviços por domínio (Acl, Admin, Ai, Auth, Billing, Dashboard,
                            Modules, Parsers, Signup, Tenant, Tenant/Viabilidade/v1, ...)
  Support/                → helpers.php (user(), language()), UserContext, TenantAppUrl, Database/
  Tenancy/                → PostgreSQLSchemaPublicManager
  Traits/                 → HasDashboardCache, LogsAudit

bootstrap/app.php         → middleware aliases, grupo 'tenant', handlers de exceção
config/                   → inclui ai.php, tenancy.php, cashier.php, scramble.php,
                            permission.php, legal.php, privacy.php
database/
  migrations/             → migrations centrais
  migrations/tenant/      → migrations dos schemas de tenant
  factories/ (+Tenant/)   → factories
  seeders/ (+Tenant/)     → seeders (planos, entitlements, módulos, RBAC, cidades)
  rbacTemplates/          → templates RBAC por plano
docs/                     → documentação técnica e planos (datada YYYY-MM-DD-*)
resources/lang/           → pt-br.json / en-us.json (chaves UPPER_SNAKE_CASE)
resources/views/          → emails/, exports/, pdf/ (Blade só para e-mail/PDF/export)
routes/
  api.php                 → rotas centrais + definição dos rate limiters nomeados
  tenant.php              → agregador das rotas tenant (registrado pelo TenancyServiceProvider)
  tenant/                 → declarações tenant modularizadas por áreas de domínio
  console.php             → schedule + comandos closure
  web.php                 → mínimo (welcome, cashier.payment, redirect /docs)
scripts/                  → pgsql/, security/, viabilidade/ (operação e auditoria)
stubs/                    → stubs de agentes/tools do Laravel AI
tests/                    → Architecture/, Unit/, Feature/ (ver seção 12)
```

> ⚠️ **Nunca crie pastas fora desta estrutura sem aprovação explícita.**

### 4. Eloquent e Banco de Dados

#### Models

- Sempre defina `$fillable` explicitamente — nunca `$guarded = []`.
- Sempre defina `$casts` para tipos não-string (datas, booleans, enums, JSON/array).
- Use os **enums do projeto** nos casts (`WorkflowStatus`, `TenantStatus`, `LegalizacaoStatus`, `ProjetoStatus`, etc.).
- Model novo vai para `Models/Central/` **ou** `Models/Tenant/` — nunca solto em `Models/` (exceções históricas: `User`, `AuditLog`, `ConsentLog`).
- Todo model precisa de **factory** (há `FactoriesSmokeTest` cobrindo as de tenant).

#### Migrations

- Migration central → `database/migrations/`; migration de tenant → `database/migrations/tenant/`. **Errar a pasta quebra o provisionamento do tenant.**
- Toda migration deve ter `down()` funcional.
- Nunca altere migration já executada em produção — crie uma nova.
- Índices em colunas de `WHERE`, `ORDER BY` e FKs. Use `foreignIdFor()`/`constrained()`.
- O banco é **PostgreSQL** (com `pgvector` nas tabelas de embeddings de IA), mas os testes rodam em **SQLite `:memory:`** — evite SQL cru específico de PostgreSQL fora de `app/Support/Database/` (ex.: `SqlDateParts` abstrai date parts por driver). Se precisar de SQL específico por driver, siga esse padrão.

#### Queries

- Nunca `all()`/`get()` sem condição em tabela grande — pagine (`paginate()`) ou limite.
- Sempre eager loading com `with()` — sem N+1.
- `select()` explícito em queries pesadas; `chunk()`/`lazy()` para volumes grandes em jobs.

### 5. FormRequests — Validação e Autorização

- **Toda rota que muta dados usa FormRequest** — os testes de arquitetura proíbem `->validate()` inline nos controllers cobertos.
- `authorize()` deve verificar permissão **de verdade** — `TenantAdminRequestAuthorizationTest` **falha o build se encontrar `return true;`** em FormRequests de `Requests/Tenant/` e `Requests/Tenant/Admin/`. Padrão do projeto: checar `user()`/roles/permissions (Spatie) ou ownership do recurso.
- Use `$request->validated()` — nunca `$request->all()`.
- Nomeie por ação + recurso (`StoreTerrenoRequest`, `UpdateLegalizacaoEtapaRequest`, `ListNegotiationsRequest`, `DestroyRoleRequest`...) e espelhe a pasta do controller.

### 6. Respostas da API — `ApiResponseService` + Resources

Este projeto tem um **envelope próprio**. Não invente formato novo:

```php
// Sucesso (ApiResponseService::success / created)
{ "success": true, "data": {...}, "message": "..." }

// Erro (ApiResponseService::error / DomainException / handlers do bootstrap/app.php)
{ "success": false, "error": { "code": "SNAKE_CASE_CODE", "message": "...", "details": {...} } }

// Validação (422) — formato híbrido mantido por compatibilidade
{ "success": false, "message": "...", "errors": {...}, "error": { "code": "VALIDATION_ERROR", ... } }

// Paginação (ApiResponseService::paginated) — formato NATIVO do Laravel
{ "data": [...], "links": {...}, "meta": {...} }
```

- Use `App\Services\ApiResponseService` (`success`, `created`, `noContent`, `paginated`, `error`, `validationError`, `notFound`, `tooManyRequests`, ...) ou retorne `Resource`/`ResourceCollection` diretamente.
- **Toda resposta com dados de model passa por um Resource** (`app/Http/Resources/`) — nunca retorne o model cru.
- Mensagens: strings `UPPER_SNAKE_CASE` são tratadas como **chaves de tradução** e resolvidas via `language()->t()` contra `resources/lang/pt-br.json` / `en-us.json`. Ao criar mensagem nova, adicione a chave nos **dois** arquivos.
- Nunca exponha campos sensíveis (senhas, tokens, ids Stripe internos) em Resources.

### 7. Tratamento de Erros e Exceções

- Exceções de domínio estendem `App\Exceptions\DomainException` (abstrata: `statusCode()` + `toResponsePayload()`) — ex.: `ViabilidadeAlreadyDecidedException`, `WorkflowTransitionNotAllowedException`, `EtapaBlockedException`, `CommitteePendingException`.
- Handlers globais ficam em **`bootstrap/app.php`** (`->withExceptions()`): `AuthenticationException` → 401 JSON, `ValidationException` → 422, `DomainException` → payload próprio, `RateLimitedException` (IA) → 429, `HttpException`/404 → códigos padronizados, fallback 500 genérico em produção.
- Nunca lance `\Exception` genérica em código de domínio; nunca exponha stack trace em produção (`APP_DEBUG=false`).

### 8. Autenticação, Autorização e RBAC

#### Fluxos de autenticação (Sanctum, tokens Bearer)

1. **Login central (broker)**: `POST /api/v1/auth/login` (domínio central) → `CentralAuthController` + `CentralLoginBrokerService` resolvem os tenants do e-mail (via `TenantUserDirectory`) → `POST /auth/select-tenant` emite um **transfer ticket** → o frontend chama `POST /api/v1/auth/exchange-ticket` no **subdomínio do tenant** e recebe o token Sanctum do tenant.
2. **Login direto no tenant**: `POST /api/v1/auth/login` no subdomínio (`TenantAuthController`/`TenantLoginService`).
3. **Login admin da plataforma**: `POST /api/v1/admin/login` (`AdminController`), protegido por `central.admin` (`EnsureUserIsAdmin`).
4. Reset de senha do tenant funciona tanto pelo domínio central quanto pelo do tenant (`TenantPasswordResetController`; URLs geradas por `App\Support\TenantAppUrl`).
- Sessões do broker expiram e são limpas por `auth:cleanup-central-login-broker` (a cada 5 min).

#### RBAC

- **Spatie Permission** (`teams => false`) nos usuários do tenant. Roles canônicas em `App\Enums\Common\RolesEnum`: `ADMIN`, `DIRECTOR`, `MANAGER`, `SUPERVISOR`, `ANALYST`, `USER`. **Sempre use o enum** — nunca strings soltas de role.
- Permissões por módulo/submódulo: `App\Enums\Common\ModulesEnum` (`admin`, `configurations`, `prospection`, `brokers`, `data`, `dashboard`, `committee`, `legal`, `negotiation`, `projects`, `reports`, `viability`, `ai`) + `SubmodulesEnum`; nomes resolvidos por `Services\Acl\PermissionNameResolver`.
- O que o tenant pode usar é a interseção de: **plano/entitlements** (middleware `check.feature`, `enforce.limits`, `subscription.active`) + **RBAC do usuário** (middleware `permission.gate`, FormRequest `authorize()`).
- Templates de permissão por plano: `database/rbacTemplates/` + `PlanRolePermissionTemplate` + comandos `rbac:apply-templates` e `tenants:sync-acl`.
- Autorização acontece **antes** do Service (rota/middleware/FormRequest). **Services nunca tratam autorização.**
- Helper `user()` (em `app/Support/helpers.php`) retorna `UserContext` — use `user()->getType()` (`UserType::SIGAPP|TENANT`), não checagens manuais de classe.

#### Aliases de middleware (bootstrap/app.php)

`force.json`, `tenant.logs`, `api.logger`, `central.context`, `tenant.context`, `auth.central`, `auth.tenant`, `enforce.limits`, `subscription.active`, `central.admin`, `tenant.admin`, `user.admin`, `permission.gate`, `check.feature`, `ai.rate_limit`, `ai.budget` — além do grupo `tenant` (`InitializeTenancyFlexible`) e do global `SecurityHeaders`.

### 9. Rotas da API

- API **versionada** em `/api/v1/`. Central em `routes/api.php`; o agregador tenant fica em `routes/tenant.php` e carrega, na ordem declarada, os módulos em `routes/tenant/`.
- Middlewares comuns, prefixo versionado e proteção de assinatura permanecem no agregador tenant. Arquivos em `routes/tenant/` declaram somente suas rotas de domínio; todo arquivo modular deve ser carregado exatamente uma vez pelo agregador. O `TenancyServiceProvider` não registra essas rotas novamente quando o cache de rotas está ativo.
- **Rate limiting é obrigatório e nomeado** — os limiters são definidos no topo de `routes/api.php` (`api-public`, `api-auth`, `central-login`, `admin-login`, `transfer-ticket`, `password-reset-*`, `signup-status`, `consent-log`, `viabilidade-approval`, ...). Rota nova entra num grupo com throttle existente ou ganha limiter próprio com resposta via `ApiResponseService::tooManyRequests()`.
- Rotas centrais ficam dentro do loop de `central_domains` em `routes/api.php`. O primeiro domínio preserva os nomes canônicos (`admin.*`); os domínios alternativos recebem o prefixo interno `central-domain-{index}.` para que `route:cache` não encontre nomes duplicados. Siga esse padrão ao criar rotas centrais nomeadas.
- Rotas tenant novas: declare `tenant.context` + `auth:sanctum` + `auth.tenant` + `throttle:api-auth`, e o gate de módulo/assinatura adequado (`check.feature:...`, `subscription.active`, `tenant.admin`, `permission.gate`).
- Use Route Model Binding e kebab-case plural nos paths. Webhook Stripe (`POST /webhook/stripe`) fica **sem** throttle/CSRF — não mexa nisso sem entender o motivo.
- Health checks: `/up` (framework), `GET /api/health` (mínimo/legado usado pelo Docker), `GET /api/v1/health` (público versionado, mínimo), `GET /api/v1/health/details` (admin central autenticado) e `GET /api/health` no tenant (autenticado, inclui dados do tenant).
- A documentação Scramble é gerada das rotas/FormRequests/Resources — mantenha tipos e PHPDocs corretos para a doc sair certa em `/docs/api`.

### 10. Billing (Cashier/Stripe)

- Entidades centrais: `Plan`, `Entitlement`, `TenantEntitlement`, `Coupon`, `WebhookEvent`, `Dispute` (em `Models/Central/`).
- Serviços em `app/Services/Billing/`: `StripeCheckoutService`, `TenantBillingService`, `BillingHistoryService`, `CouponService`, `WebhookEventService` (idempotência de webhooks via `WebhookEvent`).
- Fluxos do tenant: assinatura/portal (`TenantController@subscription`, `billingPortal`), troca de plano (`PlanSwapController`), dunning/retry de pagamento (`DunningController`), cupons (`CouponController`), histórico (`BillingHistoryController`).
- Troca de plano: **upgrade** cobra imediatamente via Stripe (`pendingIfPaymentFails()->swapAndInvoice()`) e só concede o plano local se a chamada confirmar; **downgrade** mantém `plan_id` atual e grava `scheduled_plan_id` até a renovação (`invoice.paid`). O snapshot de assinatura expõe `scheduled_plan`.
- O portal de billing deve usar `STRIPE_PORTAL_CONFIGURATION_ID` quando configurado para impedir troca de plano fora do `PlanSwapController`.
- Os Prices recorrentes dos planos são configurados por ambiente em `config/cashier.php` via `STRIPE_PRICE_BROKER`, `STRIPE_PRICE_BASICO`, `STRIPE_PRICE_MASTER` e `STRIPE_PRICE_PRO`; o `PlanSeeder` não deve embutir IDs de uma conta Stripe específica. Sem um ID configurado, o checkout cria um Price emergencial com valor em centavos.
- Enforcement de plano: middlewares `subscription.active`, `enforce.limits`, `check.feature` + `EntitlementService`/`PlanMatrixService`.
- O catálogo de features do roadmap frontend fica centralizado em
  `Database\Seeders\EntitlementSeeder::roadmapFeatureMatrix()` e segue a
  escada Broker → Básico → Master → Pro: operação individual, análise,
  gestão e recursos estratégicos/IA. `onboarding.profile` e
  `experience.accessibility` ficam disponíveis em todos os planos. A chave
  `projects.room` é o alias canônico de `projects_room` e preserva o acesso
  legado do plano Pro; não crie uma cobrança ou regra paralela para o alias.
- Nunca processe webhook Stripe fora do `WebhookController`/`WebhookEventService`; nunca confie em dados do cliente para preço/plano.

### 11. IA (Laravel AI SDK)

- **Regra de ouro: nunca integre SDK de provider diretamente.** Tudo passa pelo `laravel/ai` + `config/ai.php` (`AI_PROVIDER`: deepseek/gemini/openrouter, com fallback `AI_FALLBACK_PROVIDER` via `AiProviderRouter`).
- Agente principal: `app/Services/Ai/Agents/SIG_IA.php`. O chat é estritamente de **leitura e recomendação**: não registre tools que alterem workflow, tarefas ou arquivos; essas ações continuam nas APIs e telas próprias, com autorização e confirmação explícitas. O agente tem limites de passos/tokens e aceita failover configurado por `AI_FALLBACK_PROVIDER`/`AI_FALLBACK_AGENT_MODEL` via Laravel AI SDK. Tools em `app/Services/Ai/Tools/` (~40: dashboards, terrenos, viabilidades, comitê, legalização, documentos, insights, scoring, anomalias, previsões, geoanálise, IBGE, mercado imobiliário...). Stubs para novos agentes/tools em `stubs/`.
- Proteções obrigatórias em rotas de IA: `ai.rate_limit` (`AiRateLimit`) e `ai.budget` (`AiBudgetCheck` — orçamento por tenant, `AI_TENANT_BUDGET_DEFAULT`). Serviços e jobs que chamam provider (dossiê de comitê e relatório de terreno) também devem verificar o orçamento pelo `AiTelemetryService`. Telemetria/custo em `AiTelemetryService` + `AiRequestLog` (preços por provider no `.env`); nunca grave argumentos de tool sem passá-los pelo `AiDataRedactor`.
- Dados sensíveis passam por `AiDataRedactor`/`RedactingToolDecorator` antes de ir ao provider.
- RAG: `AiDocumentChunk`/`AiDocumentEmbedding` guardam embeddings JSON e `AiEmbeddingService` calcula similaridade de cosseno na aplicação, com limiar configurável (`AI_EMBEDDING_MIN_SIMILARITY`). O upload de documento despacha `IndexDocumentEmbeddingJob`; falha de embedding deve falhar a indexação, nunca gravar vetor nulo/zerado. Provider e modelo de embedding são `AI_EMBEDDING_PROVIDER` e `AI_EMBEDDING_MODEL`.
- Relatórios PDF gerados por IA ficam no tenant em `ai_generated_reports` (`AiGeneratedReport`, `AiGeneratedReportRepository`, `TerrenoAiReportService`) e são baixados por rota própria (`/ai/reports/{id}/download`). O serviço de relatório apenas orquestra a coleta (`TerrenoAiReportDataService`), o mapa (`TerrenoAiReportMapRenderer`) e a narrativa/fallback (`TerrenoAiNarrativeService`). Registre metadados e caminho do arquivo; não retorne caminhos internos crus ao cliente.
- A geração assíncrona de relatório de terreno usa `ai_report_generations` (`AiReportGeneration`, `AiReportGenerationService`, `GenerateTerrenoAiReportJob`). O endpoint aditivo `POST /ai/terrenos/{id}/relatorio-pdf/jobs` responde `202` e o status é consultado em `GET /ai/terrenos/{id}/relatorio-pdf/jobs/{generation}`; o endpoint síncrono legado permanece compatível durante a migração do frontend.
- Dossiês assistidos de comitê ficam em `comite_ai_dossiers` e são gerados por `CommitteeAiDossierService`/`GenerateCommitteeAiDossierJob`; não use `/ai/sig-ai` para preencher telas internas, pois esse endpoint é conversacional e grava em `agent_conversations`.
- Scoring recalculado por `ai:recalculate-scores` (agendado diariamente) / `RecalculateAiScoresJob`; o comando itera os tenants ativos e pode ser limitado com `--tenant=`.
- Streaming de chat coberto por teste (`AiChatStreamingTest`) — mantenha compatível.

### 12. Domínio Tenant — módulos principais

Fluxo macro do terreno (enum `WorkflowStatus`, orquestrado por `LandWorkflowService`/`TerrenoWorkflowService`):
`em_analise → aguardando_viabilidade → viabilidade_aprovada → aguardando_comite → negociacao_minuta → contrato_assinado → legalizando → legalizado_finalizado` (+ `descartado`, `arquivado`). Transições disparam `WorkflowTransitioned` → listeners gravam `StatusHistory`, `EntityActivity`, notificam e transicionam `Projeto`s relacionados.

- **Prospecção/Terrenos**: `TerrenoService`, filtros (`TerrenoFilterService`), export Excel, KMZ upload (`KmzParserService`), cálculo de área útil (`Services/Tenant/Area/` — topografia, hidrografia, polígonos; `CalculateUsableAreaJob`), geoproximidade, scraper/enriquecimento de portal (`PortalTerrenoScraperService`, `Services/Parsers/Hiperdados/`), proprietários, corretores externos, contatos, produtos por terreno.
- **Roadmap operacional**: atividades genéricas em `ActivityController`/`ActivityService`, tarefas colaborativas em `TaskController`/`TaskService` e cards de pipeline em `TerrenoController@pipeline`. Essas superfícies usam as tabelas existentes `entity_activities`/`tasks` e as migrations tenant `2026_07_12_000001_extend_tasks_for_collaboration`. Elas continuam protegidas pelas features `collaboration.inbox`, `collaboration.tasks` e `prospection.pipeline_board`.
- **Comparação**: comparação de 2–4 terrenos e shortlists ficam em `ShortlistController`/`ShortlistService`, com as tabelas tenant `shortlists` e `shortlist_items`. A feature `prospection.comparison` é habilitada a partir do plano Básico; o backend não transforma comparação em recomendação automática.
- **Viabilidade**: motor de cálculo em `Services/Tenant/Viabilidade/v1/` (calculators de DRE, fluxo mensal, receitas, despesas, indicadores, POC, impostos, curva). Premissas (`PremissasViabilidade` / `PremissasViabilidadeCrudService`): vigência `[vigente_em, encerrada_em]` sem sobreposição no mesmo perfil; premissa futura não invalida a vigente hoje (fecha a anterior só na véspera); seleção determinística por `vigente_em`, `versao`, `id`; exclusão de premissa referenciada em snapshot vira inativação (`DestroyPremissasViabilidadeRequest`); queries no repository. Seções, versões/auditoria, aprovação (submit/decide/revogar com rate limit próprio), comparação e duplicação. Aprovação usa enum `ViabilidadeApprovalStatus` (`pendente` → `em_aprovacao` → `aprovada`|`rejeitada`; revogação → `revogada`). Estudos `em_aprovacao`/`aprovada`/`rejeitada`/`revogada` são imutáveis; recálculo de estudo decidido cria nova versão `pendente` e preserva a anterior. Snapshot canônico (`schema_version` 2) em `premissas_snapshot` via `ViabilidadeSnapshotService` (form_values, produtos, referência `premissas.id`, hashes, engine version); `data_lancamento` é materializada na criação e `terreno_id` é imutável no update. `ViabilidadeCalculationResource` expõe `calculation_engine_version`, `warnings` e `reconciliation` na resposta padrão; snapshots antigos usam os fallbacks `null`, `[]` e `null`, respectivamente. Compra direta do terreno no fluxo de caixa é rateada mensalmente durante a obra. Campos com efeito no motor: `meses_entrega` (data de entrega), `obra_ate_lancamento` (desembolso físico pré-lançamento), `distribuicao_lucros_percentual_obra` (percentual do saldo elegível distribuído no horizonte financeiro), `variavel_correcao` (taxa anual adicional de correção, só se enviada no estudo) e `usar_antecipacao_pj` (decisão por estudo; quando falso, o percentual configurado é preservado, mas o percentual efetivo e todo o cronograma da dívida PJ são zerados). `despesas_onerosas_bancos` na DRE é saída dos juros PJ calculados, não input paralelo. Curvas oficiais em `Data/curvas_obra_aux_obras.json`; demanda CEF ponderada; dívida PJ com desembolso na demanda mínima, cronograma único e saldo final zero; TIR via XIRR. Constraints tenant: unique `(terreno_id, version)` e índices parciais de uma `is_current`/uma `aprovada` por terreno. Versões são históricas inclusive após soft delete; a próxima versão considera registros excluídos e o lock de criação/duplicação/aprovação é adquirido no terreno. Ao restaurar uma versão antiga, ela não substitui a viabilidade atual; `2026_07_13_000000_resequence_duplicate_viabilidade_versions` saneia duplicidades legadas antes da constraint e mantém auditoria dos ajustes. Tools de IA devolvem resumo financeiro. Modelo em `docs/viabilidade-modelo/`, fixtures em `tests/Fixtures/Viabilidade/`, plano em `docs/2026-07-13-plano-saneamento-e-evolucao-viabilidade.md`. **Não altere fórmulas sem validar contra a planilha modelo.**
- **Convenções de cálculo da viabilidade**: `gastos_mensais_stand` usa razão decimal (`0.0001` = `0,01%`); a curva de obra pós-lançamento incide apenas sobre o percentual restante de `obra_ate_lancamento`; o seguro é desembolsado linearmente entre lançamento e fim da obra, fora da curva física; custos por unidade preservam a fração mensal da curva de vendas; a medição CEF retém o saldo a partir de 95% da curva física e libera 55%/45% em `prazo+2`/`prazo+5`; a dívida PJ usa a base completa de obra da DRE. Para corresponder ao modelo canônico, a TIR operacional usa XIRR dos saldos operacionais acumulados e a TIR financeira usa XIRR dos saldos acumulados após funding e serviço da dívida PJ, ambos com dias reais. Em fluxo não convencional, use a única raiz não negativa; múltiplas raízes não negativas tornam a TIR ambígua (`null`). A política de caixa replica `Tab_Mestre!JA:JO`: aporta o déficit incremental enquanto o saldo livre acumulado é negativo; após completar os aportes, devolve até 25% do saldo acumulado por mês, limitada ao total aportado; reserva um mês de saídas operacionais; e distribui somente os incrementos positivos do excedente acumulado, limitados pelo percentual configurado e pelo saldo elegível final. Alterações materiais nessas convenções exigem nova versão de `ViabilidadeSnapshotService::ENGINE_VERSION` e atualização do teste/fixture da planilha canônica.
- **Premissas iniciais da viabilidade**: `PremissasViabilidadeSeeder` alinha somente o perfil CEF ao cenário canônico da planilha Cimcal Osvaldo Cruz v.02.2026. Ele cria defaults apenas quando não existe premissa CEF ativa, não sobrescreve dados existentes e mantém o perfil próprio independente. Mudanças nesses defaults exigem atualização de `PremissasViabilidadeSeederTest` e validação contra a planilha modelo.
- Cenários de viabilidade persistidos usam `ViabilidadeScenarioController`/`ViabilidadeScenarioService` e a tabela tenant `viabilidade_scenarios`. O cálculo cria uma versão transitória isolada e delega ao motor oficial `Services/Tenant/Viabilidade/v1`; não duplique fórmulas nem altere a viabilidade-base. Promoção cria uma nova versão através do fluxo de duplicação existente.
- **Comitê**: `CommitteeService` — revisões, pareceres por departamento, pendências, decisão final.
- **Negociação**: `NegotiationService` — negociações + eventos.
- **Contratos**: `ContractService`/`ContractRepository` — partes, assinatura (`ContratoSigned` → e-mail + atividade).
- **Legalização**: etapas com dependências, status, prazos (`LegalizacaoEtapaStatus`), progresso recalculável, Gantt (`SyncGanttRequest`), PDF de checklist, notificação de atraso (`tenant:notify-overdue-legalizacao-etapas`).
- **Projetos**: `ProjetoService` — ciclo próprio (`ProjetoStatus`), integrado ao workflow do terreno.
- **Dashboard/Timeline**: `DashboardQueryService` + cache (`HasDashboardCache`), `TimelineService`.
- **Mobile**: registro de devices (`MobileDeviceInstallation`), inbox de notificações, push (`MobilePushService`).
- **Cadastros**: regionais, departamentos, produtos (com auditoria/histórico `ProdutoHistorico`), usuários do tenant com `status`. O módulo/tabela `positions` foi removido do schema tenant; não reintroduza cargos/positions sem decisão explícita.

### 13. Jobs, Queues, Events e Scheduler

- Operações demoradas são assíncronas via Jobs (`app/Jobs/`). Queue: `sync` em teste, **Redis em produção**.
- **Todo Job deve implementar `failed(Throwable $e)`** — verificado por `LayerBoundariesTest::test_all_jobs_define_failed_handler`. Defina também `$tries`/`$timeout`/`$backoff`.
- Eventos de domínio em `app/Events/Tenant/` com listeners em `app/Listeners/Tenant/` registrados explicitamente no `EventServiceProvider` — a descoberta automática global está desativada em `bootstrap/app.php` para não duplicar listeners; side-effects nunca inline no Service quando houver evento adequado.
- Agendamentos ficam em **`routes/console.php`** (broker cleanup 5min, consent-logs diário, tenants pendentes por hora, verificação de storage 07:00, etapas atrasadas 08:00, digests diário/semanal, scores IA 06:00, stats de tenants por hora). Comando novo recorrente → agende ali.
- Comandos Artisan em `app/Console/Commands/` com `$signature`/`$description`; comandos destrutivos (ex.: `WipeAllTenants`) exigem confirmação explícita.

### 14. Notificações e E-mail

- Transporte: **Resend** (`RESEND_API_KEY`). Teste manual: `php artisan mail:test {email}`.
- Notificações de workflow em `app/Notifications/Workflow/` respeitam as **preferências do usuário** (`NotificationPreference` + trait `RespectsEmailPreference` + `NotificationCatalog`). Notificação nova de fluxo deve entrar no catálogo e respeitar preferências/digest (`notifications:send-email-digests`).
- Alertas de storage usam `tenant:check-storage-usage` + `StorageLimitApproachingNotification`, com thresholds persistidos em `tenants.storage_alert_threshold` (80%/90%) para evitar reenvio repetido.
- Views de e-mail em `resources/views/emails/`.

### 15. Uploads, PDF e Excel

- PDF via `spatie/laravel-pdf` (Browsershot/Chromium — env `BROWSERSHOT_CHROME_PATH`/`PUPPETEER_EXECUTABLE_PATH`); templates em `resources/views/pdf/`.
- Excel via `maatwebsite/excel` — classes em `app/Exports/` (ex.: `TerrenosExport` + `TerrenoExportRepository`).
- Upload de arquivo: valide tipo MIME, tamanho e extensão no FormRequest (ex.: `UploadKmzRequest`, `StoreDocumentoRequest`). Storage é sufixado por tenant quando local (config tenancy); documentos e relatórios de IA usam o disk `s3`. Respeite `enforce.limits:storage_gb` nas rotas que aumentam uso de armazenamento.

### 16. i18n

- Locales: `pt-br` (padrão) e `en-us`, em `resources/lang/*.json` com chaves `UPPER_SNAKE_CASE`.
- Resolução via helper `language()->t('KEY')` / `LanguageService`; locale do usuário aplicado por `SetUserLocale` e alterável via `PUT /locale`.
- Toda mensagem nova voltada ao usuário da API entra nos **dois** JSONs.

### 17. LGPD / Privacidade / Segurança

- Consentimento de cookies: `POST /api/v1/consent-log` (público, rate-limited 5/min) + retenção via `privacy:cleanup-consent-logs` (config `privacy.php`); termos de uso versionados no tenant (`TermoDeUsoVersao`). Config `legal.php`.
- Auditoria: trait `LogsAudit` + `AuditLog` (central, consultável em `/admin/audit-logs`); auditoria RBAC em `scripts/security/audit_tenant_rbac.php` e `docs/security/`.
- `SecurityHeaders` é global; **rate limiting em toda rota** (ver seção 9); `APP_DEBUG=false` em produção; nunca commite `.env` (o `.env.example` lista todas as variáveis, sem valores — **atualize-o ao criar variável nova**).
- Nunca confie em dados do cliente para permissões, preços ou tenant-id (o header `X-Tenant` só vale fora de produção).
- Rode `composer audit` periodicamente.

---

## 🧪 Testes (obrigatório)

- **PHPUnit 13 puro** (classes estendendo `Tests\TestCase`) — **não** Pest. Suites: `Architecture`, `Unit`, `Feature` (`phpunit.xml`).
- Ambiente de teste: SQLite `:memory:` (central e tenancy), queue `sync`, cache `array`, `BCRYPT_ROUNDS=4`.
- Toda funcionalidade nova exige testes **antes de ser considerada concluída**: Feature cobrindo happy path + pelo menos um cenário de erro (401/403/422), e Unit para services/calculators com lógica.
- Estrutura espelha o código: `tests/Feature/Tenant/`, `tests/Feature/Billing/`, `tests/Unit/Services/Viabilidade/`, etc.
- Padrão Arrange-Act-Assert, nomes descritivos (`test_rejeita_transicao_de_workflow_invalida`), `RefreshDatabase` quando toca o banco, `actingAs()` para rotas autenticadas.
- Mock de externos sempre: `Http::fake()`, `Mail::fake()`, `Notification::fake()`, `Queue::fake()`, `Event::fake()` — **nunca** bata em Stripe/Resend/providers de IA em teste.
- Testes tenant usam o fluxo de inicialização de tenancy dos testes existentes (siga `tests/Feature/Tenant/*` como referência) — não invente bootstrap próprio.

#### Testes de arquitetura (rodam no CI — não os enfraqueça)

| Teste | Garante |
|---|---|
| `LayerBoundariesTest` | Controllers listados sem query Eloquent direta; **todos os Jobs com `failed()`** |
| `ServicesArchitectureTest` | Services migrados sem Eloquent direto; Services sem `Illuminate\Http\Request` |
| `AdminControllerArchitectureTest` | Controllers admin sem `->validate()` inline nem queries diretas |
| `PublicControllerArchitectureTest` | Controllers públicos/auth sem validação inline; Blog sem query direta em Post |
| `ModulesControllerArchitectureTest` | ModulesController sem uso direto de Models |
| `TenantAdminRequestAuthorizationTest` | FormRequests tenant sem `authorize()` trivial (`return true;`) |
| `TenantRoutesArchitectureTest` | Módulos tenant carregados uma vez; contrato legado e precedência das rotas preservados |
| `RouteCacheArchitectureTest` | Nomes completos de rota únicos; nomes canônicos preservados no domínio central principal |

Ao criar controller/service/job novo nos escopos cobertos, ele **precisa** nascer conforme — e, quando o teste usa lista explícita de arquivos, adicione o novo arquivo à lista.

---

## 🔍 Qualidade: PHPStan e Pint

- **PHPStan nível 8** (+ `bleedingEdge`), paths `app` e `tests`, `phpVersion: 80400`. Rode `composer analyse` antes de todo commit.
- O `phpstan.neon` inclui o baseline **`phpstan.baseline.neon`** e uma lista extensa de `ignoreErrors` para a "magia" do Eloquent (o Larastan está instalado como dev-dependency, mas a extensão **não** está incluída no `phpstan.neon` — os falsos positivos são tratados via ignores/baseline). **Não adicione novos padrões ao `ignoreErrors` nem regenere o baseline para esconder erro novo** — corrija o tipo. Ignore novo só com justificativa em comentário.
- **Pint** preset `laravel`: `./vendor/bin/pint --test` deve passar limpo antes de qualquer merge.

---

## 📛 Convenções de Nomenclatura

O domínio é **em português** (Terreno, Viabilidade, Legalizacao, Negociacao, Comite, Proprietario, Corretor...) e a infraestrutura em inglês (Service, Repository, Request, Resource...). Mantenha a mistura existente — não "traduza" nomes de domínio.

| Tipo | Convenção | Exemplo real |
|---|---|---|
| Controller | PascalCase + sufixo, na pasta do contexto | `Tenant/TerrenoController.php`, `Admin/PlanAdminController.php` |
| Service | PascalCase + sufixo, pasta por domínio | `Tenant/LegalizacaoService.php`, `Billing/StripeCheckoutService.php` |
| Repository | PascalCase + sufixo (+ interface em `Contracts/`) | `Tenant/ViabilidadeRepository.php`, `Contracts/ViabilidadeRepositoryInterface.php` |
| FormRequest | Verbo + recurso + Request | `StoreTerrenoRequest`, `TransitionTerrenoWorkflowRequest` |
| Resource | Recurso + Resource | `Tenant/ViabilidadeResource.php` |
| Model | PascalCase singular, em `Central/` ou `Tenant/` | `Central/Plan.php`, `Tenant/Terreno.php` |
| Enum | PascalCase (+ `Common/` se compartilhado) | `WorkflowStatus`, `Common/RolesEnum` |
| Event | Ação no passado, `Events/Tenant/` | `ViabilidadeDecided`, `ContratoSigned` |
| Listener | Verbo + efeito, `Listeners/Tenant/` | `NotifyViabilidadeDecision`, `RecordWorkflowStatusHistory` |
| Job | Verbo + objeto | `CalculateUsableAreaJob`, `CreateFullTenantJob` |
| Command | Ação + Command; signature `dominio:acao` | `SyncTenantAclCommand` → `tenants:sync-acl` |
| Exception | Sufixo Exception, estende `DomainException` | `EtapaBlockedException` |
| Notification | Sufixo Notification | `Workflow/WorkflowTransitionedNotification` |
| Teste | Sufixo Test, espelhando a pasta | `Feature/Tenant/ViabilidadeApiTest` |
| Rota API | kebab-case plural, versionada | `/api/v1/legalizacao-etapas` |
| Chave i18n | UPPER_SNAKE_CASE nos dois JSONs | `RESOURCE_CREATED_SUCCESSFULLY` |

---

## 🔥 Regras de Prioridade Alta

1. **Nunca instale pacotes nem mude a estrutura de pastas sem listar o que faria e aguardar aprovação explícita.**
2. Prefira **recursos nativos do Laravel** antes de biblioteca externa.
3. **IA só via Laravel AI SDK** (`laravel/ai` + `config/ai.php`) — nunca SDK de provider direto; rotas de IA sempre com `ai.rate_limit` + `ai.budget`.
4. **Contexto certo**: rota/model/migration/controller novo nasce no lado certo (central × tenant). Migration de tenant em `database/migrations/tenant/`.
5. **Controllers thin** + **toda mutação via FormRequest** com `authorize()` real (teste de arquitetura falha com `return true;`).
6. **Respostas no envelope do projeto** (`ApiResponseService`/Resources) com mensagens traduzidas nos dois locales.
7. **Roles/módulos sempre via enums** (`RolesEnum`, `ModulesEnum`) — nunca strings mágicas.
8. **PHPStan nível 8** e **Pint** limpos antes de qualquer merge; não esconda erro novo em baseline/ignore.
9. **Todo Job com `failed()`** (+ `$tries`/`$timeout`/`$backoff`).
10. Funcionalidade nova = testes Feature (happy path + erro) e, quando houver lógica, Unit. Testes de arquitetura intocados.
11. `.env` nunca commitado; `.env.example` sempre atualizado ao criar variável.
12. **Feature nova ou alteração considerável deve atualizar este `AGENTS.md`** quando mudar regras, fluxos ou superfícies que a próxima IA precisa conhecer.
13. Webhook Stripe, fórmulas de viabilidade e fluxo de transfer ticket são áreas sensíveis — não altere sem entender o design atual (ver `docs/`).
14. O planejamento de projetos usa `projeto_milestones`, `projeto_dependencies` e `projeto_risks`, com mutações em `ProjetoPlanningService`; dependências devem ser validadas contra ciclos antes de persistir.
15. As rotas de milestones, dependências e riscos de projetos ficam sob `check.feature:projects.room` e devem resolver explicitamente o projeto pai antes de operar recursos aninhados.
16. O modo reunião do comitê usa `comite_meeting_*` e deve chamar o `CommitteeService` existente para qualquer decisão; fechar uma sessão não inventa decisão para pauta pendente.
17. O deal room estende negociação/contrato com ofertas, aprovações e condições; aceitar uma oferta não assina contrato. A referência documental de condições permanece opcional até existir tabela tenant canônica de documentos.
18. A central de legalização reutiliza etapas e dependências existentes. O caminho crítico deve detectar ciclos e custos realizados só podem ser derivados de itens marcados como pagos; custo comprometido permanece indisponível sem lançamento fonte.
19. Captura mobile usa `mobile_captures`/`mobile_capture_attachments`: `client_id` é UUID idempotente por usuário, toda sincronização exige `base_version` e conflitos respondem `409` com payload seguro. Anexos são multipart em storage privado; nunca aceite foto/áudio inline em base64.
20. Onboarding usa catálogo servidor versionado em `UserOnboardingService`, eventos allowlisted e idempotentes em `user_onboarding_events`; não aceite nomes livres de evento nem use onboarding para liberar permissões ou rotas.
21. Relatórios configuráveis usam `report_templates`/`report_runs`, catálogo fechado de datasets/métricas/dimensões e `GenerateReportRunJob`. O JSON do template nunca vira SQL; a execução persiste snapshot, fonte/as-of, expiração e erro seguro.
22. Inteligência documental estende `terreno_documentos` com versões imutáveis, análises assíncronas e revisão humana. OCR/análise não pode alterar campos de negócio automaticamente; limitações e confiança devem ser expostas e arquivo corrente nunca deve ser sobrescrito sem versão.

---

## 📋 Checklist antes de cada PR

- [ ] `composer analyse` (PHPStan nível 8) sem erros novos
- [ ] `./vendor/bin/pint --test` sem pendências
- [ ] `composer test` verde, incluindo `--testsuite=Architecture`
- [ ] Rota nova: versionada (`/api/v1/...`), no arquivo certo (central × tenant), com `throttle` e middlewares de contexto/permissão
- [ ] Mutação usa FormRequest com `authorize()` real; nenhum `$request->all()` / `->validate()` inline
- [ ] Resposta via `ApiResponseService`/Resource; chaves i18n adicionadas em `pt-br.json` **e** `en-us.json`
- [ ] Sem N+1 (`with()`), sem `all()`/`get()` ilimitado
- [ ] Migration na pasta certa, com `down()` funcional e índices; compatível com SQLite nos testes
- [ ] Job novo com `failed()`, `$tries`, `$timeout`
- [ ] Model novo com `$fillable`, `$casts` e factory
- [ ] Repository novo com interface? Bind registrado no `AppServiceProvider`
- [ ] `.env.example` atualizado se criou variável de ambiente
- [ ] `AGENTS.md` atualizado se a mudança alterou arquitetura, fluxos, rotas, comandos, env/deploy, billing, IA, RBAC, storage ou regras de teste
- [ ] Serviços externos mockados nos testes (Stripe, Resend, IA, HTTP)

---

**Última atualização:** Julho 2026 — saneamento de viabilidade (enum de aprovação, snapshot canônico v2, imutabilidade, constraints, resumo para IA), storage local/S3 e alertas de uso, `scheduled_plan` em billing, relatórios PDF de IA, cenários de viabilidade, planejamento de projetos, reuniões de comitê, deal room, insights de legalização, workspace, IA contextual, relatórios configuráveis, captura mobile, onboarding, versões/análise documental e regra de manutenção contínua deste documento.
