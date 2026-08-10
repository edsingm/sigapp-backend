# Arquitetura, camadas e contratos de API

> **Quando ler:** camadas Controller/Service/Repository, pastas, Eloquent/migrations, FormRequest, envelope da API, exceções, i18n, nomenclatura.
> **Hub:** [`AGENTS.md`](../../AGENTS.md)
> **Verificado por:** `tests/Architecture/LayerBoundariesTest.php`, `ServicesArchitectureTest.php`, e afins.

## 1. PHP e padrões de código

- PHP mínimo: **8.4** — use os recursos modernos da linguagem.
- Seguir **PSR-12** (estilo) e **PSR-4** (autoload). A formatação é aplicada via **Laravel Pint** (`./vendor/bin/pint`) — nunca formate manualmente nem discuta estilo em review; o Pint é a fonte da verdade.
- **Sempre declare tipos** em propriedades, parâmetros e retornos — nunca omita.
- Use **enums** nativos (ver `app/Enums/`) ao invés de constantes mágicas ou strings avulsas. Enums compartilhados entre central e tenant ficam em `app/Enums/Common/`.
- Use **readonly properties** e **constructor promotion** onde aplicável.
- Nunca use `mixed` quando um tipo preciso é possível.
- Nunca use `@phpstan-ignore` sem comentário explicativo; prefira corrigir o tipo.
- Novos arquivos de domínio devem usar `declare(strict_types=1);` (padrão dos arquivos mais recentes, ex.: `DomainException`).

## 2. Arquitetura: Controller → Service → Repository

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

## 3. Estrutura de pastas (real)

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

## 4. Eloquent e banco de dados

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

## 5. FormRequests — validação e autorização

- **Toda rota que muta dados usa FormRequest** — os testes de arquitetura proíbem `->validate()` inline nos controllers cobertos.
- `authorize()` deve verificar permissão **de verdade** — `TenantAdminRequestAuthorizationTest` **falha o build se encontrar `return true;`** em FormRequests de `Requests/Tenant/` e `Requests/Tenant/Admin/`. Padrão do projeto: checar `user()`/roles/permissions (Spatie) ou ownership do recurso.
- Use `$request->validated()` — nunca `$request->all()`.
- Nomeie por ação + recurso (`StoreTerrenoRequest`, `UpdateLegalizacaoEtapaRequest`, `ListNegotiationsRequest`, `DestroyRoleRequest`...) e espelhe a pasta do controller.

## 6. Respostas da API — `ApiResponseService` + Resources

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
- Nunca exponha campos sensíveis (senhas, tokens, ids Stripe internos) em Resources públicos/tenant. Recursos exclusivos do admin central podem expor o `stripe_price_id` necessário para operar o catálogo.

## 7. Tratamento de erros e exceções

- Exceções de domínio estendem `App\Exceptions\DomainException` (abstrata: `statusCode()` + `toResponsePayload()`) — ex.: `ViabilidadeAlreadyDecidedException`, `WorkflowTransitionNotAllowedException`, `EtapaBlockedException`, `CommitteePendingException`.
- Handlers globais ficam em **`bootstrap/app.php`** (`->withExceptions()`): `AuthenticationException` → 401 JSON, `ValidationException` → 422, `DomainException` → payload próprio, `RateLimitedException` (IA) → 429, `HttpException`/404 → códigos padronizados, fallback 500 genérico em produção.
- Nunca lance `\Exception` genérica em código de domínio; nunca exponha stack trace em produção (`APP_DEBUG=false`).

## 16. i18n

- Locales: `pt-br` (padrão) e `en-us`, em `resources/lang/*.json` com chaves `UPPER_SNAKE_CASE`.
- Resolução via helper `language()->t('KEY')` / `LanguageService`; locale do usuário aplicado por `SetUserLocale` e alterável via `PUT /locale`.
- Toda mensagem nova voltada ao usuário da API entra nos **dois** JSONs.

## Convenções de nomenclatura

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
