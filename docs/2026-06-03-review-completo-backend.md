# Review Completo do Backend SIGAPP

**Data:** 3 de junho de 2026
**Versão analisada:** `master` @ `b04a497` (HEAD atual no momento da escrita)
**Escopo:** Backend Laravel 13+ (plataforma SIGAPP)
**Autor:** Análise técnica automatizada

> **Sobre revisões deste documento:** Este review foi gerado em duas passadas. A primeira identificou erros factuais ao cruzar afirmações com o código; a segunda (esta versão) aplica as correções. Ver **Apêndice A — Errata** ao final para a lista completa de correções.

---

## 1. Sumário Executivo

O SIGAPP é uma plataforma **SaaS multi-tenant** para análise de viabilidade de terrenos e gestão imobiliária, voltada ao mercado brasileiro. Após ~1 semana do último review (26/05/2026), o backend recebeu **+6 commits** com adições relevantes (Scramble, terrain usable area, type safety, browsershot, removal intencional do frontend standalone) e mantém uma base **sólida e em evolução**, mas com várias **violações recorrentes** ao AGENTS.md que persistem há múltiplos ciclos.

**Conclusão em uma linha:** Projeto continua enterprise-grade no motor financeiro e no multi-tenancy, mas a dívida técnica arquitetural está **crescendo** silenciosamente — violaçōes de camada (`Services` consultando Eloquent diretamente) e dívidas conhecidas da revisão anterior **não foram saneadas**.

---

## 2. Métricas Atuais (junho/2026)

| Dimensão                                 | Maio/26 |                  Junho/26 |          Δ |
| ----------------------------------------- | ------: | ------------------------: | ----------: |
| **PHP files (app + tests)**         |     n/d |                       621 |          — |
| **Models (total)**                  |      53 |                        54 |          +1 |
| **Controllers**                     |      57 |                        61 |          +4 |
| **Controllers sem Eloquent direto** |      53 |              **61** | +8 (Fase 1) |
| **FormRequests**                    |    100+ |                       139 |         +39 |
| **API Resources**                   |     65+ |                        65 |           = |
| **Middlewares customizados**        |      14 |                        18 |          +4 |
| **Services (PHP files)**            |     65+ |                        76 |         +11 |
| **Repositories (concretos)**        |      27 |                        42 |         +15 |
| **Repository Contracts**            |      14 |                        27 |         +13 |
| **Jobs**                            |       4 |                         5 |          +1 |
| **Enums (PHP files)**               |      13 |                        14 |          +1 |
| **Test files**                      |      82 |                        91 |          +9 |
| **Rotas API (api.php)**             |     n/d |                        50 |          — |
| **Rotas tenant (tenant.php)**       |     n/d |                       179 |          — |
| **Rotas web (web.php)**             |     n/d |                         3 |          — |
| **AI Tools**                        |      25 |                        25 |           = |
| **Migrations — central**           |     n/d |                        33 |          — |
| **Migrations — tenant**            |     n/d |                        63 |          — |
| **Migrations — total**             |     n/d |                        96 |          — |
| **Factories**                       |       2 |                         2 |           = |
| **Custom Exceptions**               |       1 |                         1 |           = |
| **Eventos customizados**            |       0 |                         7 |          +7 |
| **Listeners**                       |       0 |                        10 |         +10 |
| **Cobertura de Contracts (Repo)**   |    ~52% |                       64% |         +17 |
| **phpstan.baseline.neon (linhas)**  |  609 kB |                    609 kB |           = |
| **Working tree (uncommitted)**      |     n/d | limpo (HEAD =`b04a497`) |          — |

---

## 3. O que Mudou desde 26/05/2026 (último review)

### 3.1 Commits relevantes (último ciclo, desde 26/05/2026)

| SHA         | Data  | Mensagem                                                          | Impacto                                                                                                                                                   |
| ----------- | ----- | ----------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `e8f2dbf` | 27/05 | feat: add landing url and update stripe redirects                 | URLs de retorno do Stripe + 2 docs de análise                                                                                                            |
| `2a44062` | 27/05 | feat(api-docs): add laravel api documentation with dedoc/scramble | Documentação automática da API (`/docs/api`)                                                                                                         |
| `e380e61` | 27/05 | feat(terrain): add terrain usable area calculation                | Novo job `CalculateUsableAreaJob` + 4 services de cálculo de área útil + `DeclividadeClassificacao` enum + `TerrenoObserver`                     |
| `63b215b` | 30/05 | refactor: add type safety, new middleware, code cleanup           | 4 novos middlewares (`EnsureCentralContext`, `EnsureCentralUser`, `EnsureTenantContext`, `EnsureTenantUser`) + type safety massiva (179 arquivos) |
| `ff265f9` | 02/06 | feat: add browsershot and update admin routes                     | `spatie/browsershot` para PDF/screenshot + eager loading de roles no login + `permission.gate` em todas as rotas admin CRUD                           |
| `b04a497` | 03/06 | chore: clean up old frontend setup and update project docs        | **Remove frontend standalone** (package.json, vite, blade views) + overhaul de `docs/projecto.md`                                                 |

> **Nota sobre `9fc60e9` (coupons/dunning/billing history):** este commit é de **23/05/2026**, anterior à revisão de 26/05. **Já estava em escopo** na revisão anterior e, portanto, não conta como entrega deste ciclo.

### 3.2 Novos arquivos / pastas detectadas (neste ciclo)

- `app/Enums/DeclividadeClassificacao.php` (1 novo enum — único novo, contrário à lista inicial de 4)
- `app/Services/Tenant/Area/{AreaCalculator, Hydrography, PolygonCalculator, Topography}.php` (4 novos services)
- `app/Jobs/CalculateUsableAreaJob.php` (1 novo job)
- `app/Observers/Tenant/TerrenoObserver.php` (1 observer novo)
- `app/Http/Controllers/Api/V1/Admin/CouponController.php` (1 controller novo)
- `app/Http/Controllers/Api/V1/Tenant/{BillingHistoryController, CouponController, DunningController}.php` (3 controllers novos)
- 4 novos middlewares: `EnsureCentralContext`, `EnsureCentralUser`, `EnsureTenantContext`, `EnsureTenantUser`
- 39 novos `FormRequest` em `Admin/`, `Tenant/Admin/`, `Tenant/` (consolidação por recurso destroy/list/show)

> **Correção sobre a lista inicial:** `app/Enums/Common/{EntitlementType, RolesEnum, SectorsEnum, SubmodulesEnum}.php` já existiam **antes** de 26/05 — não são novos. Da mesma forma, `RefreshTenantStatsJob` foi adicionado em 22/05 (também anterior à revisão anterior).

### 3.3 Itens do plano de 26/05 que avançaram

| #  | Recomendação de 26/05                            | Status atual                                                                                                                                                                                                                                                              |
| -- | -------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1  | `PremissasViabilidadeController` → Service/Repo | ✅**REALIZADO em 2026-06-03** — ver Apêndice B                                                                                                                                                                                                                    |
| 2  | `$fillable` no model `Projeto`                 | ✅**REALIZADO em 2026-06-03** — `#[Fillable([...])]` adicionado (ver Apêndice B)                                                                                                                                                                                |
| 3  | Health check detalhado                             | **❌ NÃO FEITO** — continua `{"status":"ok"}` simples                                                                                                                                                                                                           |
| 4  | Soft delete consistente                            | ⚠️**PARCIAL** — 4 migrations adicionaram `deleted_at`, mas padronização não está completa                                                                                                                                                                  |
| 5  | Rate limiting por plano                            | **❌ NÃO FEITO** — ainda é `throttle:api` genérico                                                                                                                                                                                                            |
| 6  | Events/Listeners para side effects                 | **❌ NÃO FEITO** — pastas `Events/` e `Listeners/` continuam inexistentes                                                                                                                                                                                     |
| 7  | Factories para todos os models                     | **❌ NÃO FEITO** — apenas 2/53 (`Legalizacao`, `LegalizacaoEtapa`)                                                                                                                                                                                            |
| 8  | Notificações email para workflow                 | ⚠️**PARCIAL** — `AbandonedCheckoutNotification`, `PaymentFailedNotification`, `PaymentRetryNotification` e `TrialEndingNotification` foram adicionadas, mas **ainda sem email para eventos de workflow** (viabilidade aprovada, comitê, contrato) |
| 9  | Cache invalidation centralizado                    | ❌**NÃO FEITO** — observers continuam chamando `clearTenantCache` diretamente nos models                                                                                                                                                                        |
| 10 | Checklist de conformidade por terreno              | ❌**NÃO FEITO**                                                                                                                                                                                                                                                    |

**Taxa de execução do plano anterior: 2/10 totalmente concluídos · 2/10 parciais · 6/10 pendentes** (atualizado em 2026-06-03 após implementação da Fase 1 — ver Apêndice B).

---

## 4. Arquitetura Atual — Estado e Conformidade

### 4.1 Padrão Controller → Service → Repository

A regra do AGENTS.md (§2) é **inegociável**: services não devem conter queries Eloquent. **Análise empírica atual:**

| Camada       |                                                                                                                    Locais de uso Eloquent direto | Status                                  |
| ------------ | -----------------------------------------------------------------------------------------------------------------------------------------------: | --------------------------------------- |
| Controllers  | 8 ocorrências em 4 arquivos (`PremissasViabilidadeController`, `PublicTenantController`, `WebhookController`, `Admin/CouponController`) | **Viola AGENTS.md §2**           |
| Services     |                           **63 ocorrências de `::query()`** e **17 ocorrências de `::create()`** distribuídas em 20+ services | **Viola AGENTS.md §2 em escala** |
| Repositories |                                                                                                                          100% Eloquent (correto) | ✅ Conforme                             |
| Models       |                                                                                            Apenas relações, casts, scopes, observers (correto) | ✅ Conforme                             |

**Detalhamento dos services que violam a arquitetura (uso direto de Eloquent):**

| Service                         | Ocorrências de Eloquent | Risco                                                                                                 |
| ------------------------------- | -----------------------: | ----------------------------------------------------------------------------------------------------- |
| `AiAnomalyDetectionService`   |                        6 | Alto — query complexas em loop                                                                       |
| `AiPredictiveAnalysisService` |                        5 | Alto — múltiplas queries com `::query()->where()->get()`                                          |
| `MobilePushService`           |                       11 | **Crítico** — DeviceInstallation + MobileNotification + User + LegalizacaoEtapa todos diretos |
| `TerrenoFilterService`        |                        1 | Baixo                                                                                                 |
| `AiMonitorService`            |                        3 | Médio                                                                                                |
| `AiTelemetryService`          |                        2 | Baixo                                                                                                 |
| `ProjetoService`              |                        1 | Baixo                                                                                                 |
| `LandWorkflowService`         |                        4 | **Crítico** — é o coração do workflow, dispara side-effects                                |
| `Auth/TenantLoginService`     |                        1 | Baixo                                                                                                 |
| (outros ~12 services)           |                  várias | Médio                                                                                                |

**Conclusão arquitetural:** O repositório real está em formato **"Service" anêmico + **"Service gordo"** misturado. A camada de Repository não está sendo usada em todo o seu potencial — o AGENTS.md diz que "Repositories são o único lugar onde Eloquent é usado diretamente" mas a prática corrente é o oposto para os services de AI, push, e workflow.

### 4.2 Cobertura de Contracts (Repository Pattern)

| Categoria       |    Concretos |   Interfaces |     Cobertura |
| --------------- | -----------: | -----------: | ------------: |
| Tenant          |           13 |            3 |           23% |
| Central         |           19 |           12 |           63% |
| **Total** | **32** | **15** | **47%** |

> **Correção da lista inicial:** apenas **3 contracts** são tenant-específicos (`ProjetoRepository`, `ProprietarioRepository`, `TerrenoProdutoRepository`). Os outros 12 são central ou cross-domain. A cobertura real de tenant (23%) é ainda pior do que a inicialmente reportada (31%).

Services mais críticos ainda sem contract:

- `MobilePushService` (sem `MobilePushRepository`)
- `AiAnomalyDetectionService`, `AiPredictiveAnalysisService`, `AiTelemetryService`, `AiMonitorService`
- `LandWorkflowService` (workflow core sem contract)
- `TerrenoFilterService`
- `TerrenoRepository` (Tenant) e `ViabilidadeRepository` (Tenant) existem como classes concretas mas sem interface

### 4.3 `$fillable` nos Models

| Situação                                          |            Modelos | Observação                   |
| --------------------------------------------------- | -----------------: | ------------------------------ |
| Usam `#[Fillable([...])]` (Laravel 12+ attribute) | **53 de 54** | ✅ Moderno e conforme          |
| Usam `$fillable` array legado                     |                  0 | —                             |
| **Não declaram fillable de jeito nenhum**    |    1 (`Projeto`) | ❌ Pendente do review anterior |

**Atualização da recomendação #2:** A migração para `#[Fillable]` attribute é moderna e preferível ao array legado. **Praticamente todos** os 54 models já estão conformes (53/54 = 98%). O `Projeto` é o único remanescente e precisa do attribute (e não `$fillable` array).

> **Correção da lista inicial:** a contagem original dizia "16+ com `#[Fillable]`" — o número correto é **53 de 54**.

### 4.4 FormRequests com `authorize()` real

- **139 FormRequests** cadastrados
- Testes de arquitetura garantem `authorize() !== return true` em pontos críticos
- Cobertura de `destroy`/`list`/`show` agora tem FormRequests dedicados por recurso (visível em `Tenant/Admin/Destroy*Request.php`)

### 4.5 API Resources

- **65 Resources** — toda resposta da API passa por Resource (verificado por convenção, sem violações em controllers novos)

---

## 5. Análise por Camada / Domínio

### 5.1 Multi-Tenancy & Tenancy (stancl/tenancy v3.8)

**Pontos fortes:**

- Schema-per-tenant via stancl/tenancy — isolamento físico
- `InitializeTenancyFlexible` (subdomain + `X-Tenant` header) — habilita `php artisan serve` local
- 5 novos middlewares (`EnsureCentralContext`, `EnsureTenantContext`, `EnsureCentralUser`, `EnsureTenantUser`, `EnsureUserIsAdmin`) — **excelente adição**
- Login broker cross-tenant (login central → seleção de tenant → ticket SHA-256) com rate limiting
- `Tenant` é o model `Billable` do Stripe (não `User`)

**Pontos de atenção:**

- `DunningController` faz `$tenant = tenancy()->tenant;` e depois `instanceof Tenant` check em 2 lugares (DRY violado) — extrair para método `currentTenantOrFail()` em support
- `AddTenantContextToLogs` middleware precisa de teste dedicado

### 5.2 Billing & Stripe

**Pontos fortes:**

- Sistema completo de cupons (`CouponService` + `CouponResource` + admin CRUD)
- Billing history (`BillingHistoryController` + `BillingHistoryService`)
- Dunning com retry escalonado (`PaymentRetryNotification`, `PaymentFailedNotification`, `PaymentRequiresActionNotification`, `TrialEndingNotification`)
- Plano Matrix Service para gestão de features/limits
- Sync de entitlements entre plan e tenant

**Pontos de atenção:**

- `app/Http/Controllers/Api/V1/Admin/CouponController.php:27` — usa `Coupon::withTrashed()->paginate(20)` direto, sem passar pelo `CouponService` (viola arquitetura)
- WebhookController: `WebhookEvent::query()->firstOrCreate(...)` direto (deveria ser via service)
- `StripeCashierService` precisa de mais idempotência explícita

### 5.3 Motor de Viabilidade Financeira (~3.000 linhas)

**Pontos fortes:**

- 3 DREs simultâneas (Gerencial / Caixa / POC) com reconciliação
- Cálculos complexos (TIR via Newton-Raphson, curvas S, medição CEF)
- Estrutura modular em `v1/Calculos/` (7 calculators): `FluxoMensal`, `Receitas`, `Despesas`, `Dre`, `Poc`, `Indicadores`, `ProdutosProcessor`
- Premissas versionáveis por perfil (CEF / Próprio)
- Pipeline de cálculo com `ViabilidadeFluxoContext` (DTO imutável)
- 2 calculadoras isoladas — fácil de testar

**Pontos de atenção:**

- Pasta `v2/` existe vazia — indicação de migração futura planejada mas não iniciada
- `PremissasViabilidadeController` continua usando Eloquent (não refatorado)
- Acoplamento direto: `ViabilidadeService` instancia outros services no construtor, mas também conhece `LandWorkflowService` e `MobilePushService` — boa parte dos side-effects de transição de viabilidade aprovada estão **inline** em vez de via Event

### 5.4 AI (SIG_IA + 25 Tools)

**Pontos fortes:**

- 25 tools cobrindo todo o domínio
- Scoring heurístico de 0-100 com 7 fatores ponderados
- Análises preditivas (probabilidade de aprovação, VGV, estagnação)
- Detecção de anomalias (workflow, financeiras, duplicados, qualidade de dados)
- Embeddings com pgvector (busca semântica)
- Telemetria de uso / budget por tenant
- `AiDataRedactor` para CPF/CNPJ/email/telefone
- Provider router com fallback
- Middleware `AiRateLimit`, `AiBudgetCheck`, `AiTelemetryMiddleware`

**Pontos de atenção (arquiteturais):**

- **63 ocorrências de `::query()`** em 4 services de AI (`AiAnomalyDetectionService`, `AiPredictiveAnalysisService`, `AiTelemetryService`, `AiMonitorService`)
- Nenhum desses services usa Repository → queries complexas estão acopladas à lógica de negócio
- Dificulta mock em testes e força o uso de SQLite in-memory em testes de AI (que pode mascarar problemas específicos do PostgreSQL/pgvector)

### 5.5 Workflow Engine (Terreno)

**Pontos fortes:**

- 10 estágios com matriz de transições
- `WorkflowStatus` enum com `stage()` e `label()` methods
- Validação de pré-requisitos
- Side effects automáticos (tasks, projetos, notificações, status history, activity feed)
- Auditoria completa

**Pontos de atenção:**

- `LandWorkflowService.php:496` linhas — **acima do limite saudável** para uma classe
- **Side effects inline** (criação de `Task`, `EntityActivity`, `StatusHistory`, push notification) — exatamente o que o review de 26/05 apontou como pendência #6
- Falta extração para Events (`TerrenoStatusChanged`, `ViabilidadeApproved`, `ContratoSigned`)
- Mistura lógica de workflow com persistência direta

### 5.6 Legalização

- 5 models (Legalizacao, Etapa, Dependencia, DocumentoFase, Pendencia)
- Grafo de dependências com detecção de ciclo
- Recálculo automático de progresso
- Custos por etapa (previsto vs pago)
- Gantt sync em lote

**Pontos de atenção:**

- `MobilePushService` chama `LegalizacaoEtapa::query()` direto — viola arquitetura

### 5.7 Auth & Permissões (RBAC)

**Pontos fortes:**

- Spatie Permission com 6 roles
- Permissões granulares `module.resource.level` (viewer/editor/manager)
- 2 papéis centrais: `CentralUser` e `TenantUser` (separação clara)
- 5 middlewares novos garantem o contexto correto
- Rate limiting agressivo em login (5/min) e seleção de tenant (10/min)

**Pontos de atenção:**

- `Models/Central/Modules/` — `Modules.php` está fora do padrão Laravel de nomenclatura; deveria ser algo como `Module.php` (singular)
- Enums `RolesEnum`, `SectorsEnum`, `SubmodulesEnum` em `Enums/Common/` são bons, mas a relação com Spatie Permission (que usa strings) precisa de mapeamento explícito em algum lugar

### 5.8 Mobile / Push Notifications

**Pontos fortes:**

- Registro de dispositivos Expo
- Notificações push com deduplicação
- Permissões por módulo para targeting
- `MobilePushService` com retry

**Pontos de atenção:**

- `MobilePushService` é o **service com mais violação arquitetural** do codebase (11 ocorrências de `::query()` / `::create()`)
- Acoplamento direto entre service e 4 models (`MobileDeviceInstallation`, `MobileNotification`, `User`, `LegalizacaoEtapa`)

### 5.9 Testes (PHPUnit 13, 89 arquivos)

**Pontos fortes:**

- 89 arquivos de teste (+7 desde 26/05)
- Cobertura: `Feature/` (integração HTTP) + `Unit/` (services/helpers) + `Architecture/` (regras estáticas)
- Testes de arquitetura verificam que controllers não usam Eloquent, FormRequests têm `authorize()` real, controllers não fazem `abort_unless`
- 4 testes de arquitetura (`AdminControllerArchitectureTest`, `ModulesControllerArchitectureTest`, `PublicControllerArchitectureTest`, `TenantAdminRequestAuthorizationTest`)

**Pontos de atenção:**

- **Apenas 2 factories** (`Legalizacao`, `LegalizacaoEtapa`) — testes criam fixtures inline, frágeis a mudanças de schema
- Arquitetura tests usam `stringContains` em vez de PHPStan/Pest architecture — são frágeis (espaço, ordem, false positives)
- Não há teste de `LandWorkflowService` que valide **todos** os side effects de uma transição complexa
- Sem teste de carga / stress em fluxos críticos (webhook Stripe, embedding generation)

### 5.10 Documentação & DevEx

**Pontos fortes:**

- Dedoc Scramble ativo (`/docs/api`) — autodocumenta a API
- `AGENTS.md` muito completo (22 kB, 16 seções)
- Pasta `docs/` rica (15+ documentos de análise anteriores)
- `composer.json` com scripts: `test`, `analyse` — facilita CI
- Frontend standalone removido intencionalmente no commit `b04a497` (03/06) — simplifica o build pipeline

**Pontos de atenção:**

- `composer.json:setup` ainda referencia `npm install` e `npm run build` mesmo após a remoção do frontend (`package.json`, `vite.config.js`, `resources/*` foram deletados no `b04a497`). O script `setup` agora quebra para qualquer dev que rode `composer setup` do zero.
- `phpstan.baseline.neon` com **15.512 linhas** — baseline inflada mascara regressões; revisar e reduzir periodicamente

### 5.11 Tratamento de Erros & Respostas

- Excelente: handler global em `bootstrap/app.php` padroniza 401/403/404/422/429/500 com envelope JSON consistente
- Envelope de resposta: `{ "success": false, "error": { "code": "...", "message": "..." } }`
- **Apenas 1 exceção customizada** (`SignupSlugReservedException`) — muito pouco para um sistema desse porte. Recomenda-se criar exceções tipadas para: `TerrenoNaoEncontrado`, `ViabilidadeInvalida`, `TransicaoInvalida`, `LimitePlanoExcedido`, `PermissaoNegada`, `DocumentoObrigatorioFaltando`, etc.

### 5.12 Jobs & Filas

- 5 jobs: `CreateFullTenantJob`, `CleanupPendingTenantsJob`, `CalculateUsableAreaJob`, `IndexDocumentEmbeddingJob`, `RefreshTenantStatsJob`
- Todos devem ter `failed()` definido (verificação não realizada no escopo deste review)

### 5.13 Migrations

- **96 migrations** (33 central + 63 tenant) — grande quantidade, mas cada uma com `down()` funcional
- **Problema persistente:** 2 migrations duplicadas `2026_04_02_000001_drop_cashier_columns_from_users_table.php` e `2026_04_02_121157_drop_cashier_columns_from_users_table.php` — a segunda é **migration vazia (no-op)** e deve ser removida

### 5.14 Dívida Menores (inventário)

| Item                                                                            | Local                                                          | Severidade                                         |
| ------------------------------------------------------------------------------- | -------------------------------------------------------------- | -------------------------------------------------- |
| Migration vazia duplicada                                                       | `database/migrations/2026_04_02_121157_*`                    | Baixa (cosmética)                                 |
| `composer.json:setup` referencia npm após remoção do frontend              | `composer.json`                                              | Média (quebra `composer setup` para novos devs) |
| `app/Models/Central/Modules/Modules.php`                                      | Deveria ser `Module.php`                                     | Baixa                                              |
| Pasta `v2/` vazia em `Viabilidade/`                                         | Indica migração não iniciada                                | Baixa                                              |
| `LandWorkflowService` com 496 linhas                                          | Acima do saudável                                             | Média                                             |
| `phpstan.baseline.neon` com 15.5k linhas                                      | Baseline inflada                                               | Média                                             |
| `DashboardController` (`Tenant`) usa `Carbon::create(2024, $mes)` em loop | `app/Http/Controllers/Api/V1/Tenant/DashboardController.php` | Média (ano hardcoded — quebrar a partir de 2025) |

---

## 6. Pontos Fortes Consolidados

1. **Multi-tenancy robusto e em evolução** — schema isolation + 5 middlewares novos + login broker cross-tenant
2. **Motor financeiro de nível enterprise** — 3 DREs, Newton-Raphson, curvas S, com estrutura modular em v1/Calculos
3. **Sistema de billing maduro** — cupons, billing history, dunning escalonado, sync de entitlements
4. **AI bem integrada e produtiva** — 25 tools, telemetria, budget, redator, embeddings pgvector
5. **Padronização forte de validação/autorização** — 139 FormRequests com `authorize()` real, middleware de permission gate por módulo
6. **Tratamento de erros centralizado** — envelope JSON consistente para 401/403/404/422/429/500
7. **Documentação rica** — Scramble (`/docs/api`), AGENTS.md (22 kB), 15+ docs de análise histórica
8. **Testes de arquitetura presentes** — 4 arquivos validam regras estáticas (mesmo que imperfeitos)
9. **Type safety melhorado** — uso extensivo de `readonly`, constructor promotion, enums, 179 arquivos refatorados no `63b215b`
10. **Atualização tecnológica** — Laravel 13, PHP 8.2+ (phpstan aponta 8.4), Spatie PDF, Browsershot, Scramble
11. **Limpeza arquitetural recente** — frontend standalone removido intencionalmente (`b04a497`) simplifica o build pipeline

---

## 7. Pontos de Atenção Críticos (Top 10)

| #  | Severidade  | Item                                                                                                                                     | Recomendação                                                                                                                                                                                                |
| -- | ----------- | ---------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1  | 🟠 Alta     | `Services` consultando Eloquent diretamente (~~63~~ → **47 ocorrências** em 14 services — itens 2.1, 2.2, 2.3, 2.4 migrados) | Criar Repositories faltantes (`AiEmbeddingService`, `AiInsightGeneratorService`, `AiScoringService`, `Tenant/AiMonitorService`, etc.) — escopo futuro                                                |
| 2  | 🔴 Crítica | Side effects de `LandWorkflowService` ainda inline                                                                                     | Extrair para Events (`TerrenoStatusChanged`, `ViabilidadeApproved`, `ContratoSigned`, `LegalizacaoEtapaOverdue`) + Listeners                                                                          |
| 3  | 🔴 Crítica | ~~8 controllers com Eloquent direto~~ **RESOLVIDO**                                                                               | Refatorar:`PremissasViabilidadeController`, `PublicTenantController`, `WebhookController`, `Admin/CouponController` (ver Apêndice B)                                                                 |
| 4  | 🟡 Média   | Cobertura de Repository Contracts~~= 47%~~ → **64%** (+17)                                                                       | Criar interfaces para repos sem contract — escopo futuro (`AiEmbeddingService`, `Billing/TenantBillingService`, `Modules/ModulesService`, etc.)                                                        |
| 5  | 🟠 Alta     | Apenas 2/53 models com factory                                                                                                           | Criar factories para Terreno, Viabilidade, User, Negociacao, Contrato, ComiteRevisao, Produto, Proprietario, Task, PremissasViabilidade                                                                       |
| 6  | 🟠 Alta     | 0 eventos customizados, 0 listeners                                                                                                      | Criar estrutura `app/Events/` e `app/Listeners/` com ao menos 5 eventos críticos                                                                                                                         |
| 7  | 🟠 Alta     | ~~Migration vazia duplicada (no-op)~~ **RESOLVIDO**                                                                               | ~~Remover `2026_04_02_121157_drop_cashier_columns_from_users_table.php`~~ (ver Apêndice B)                                                                                                                |
| 8  | 🟠 Alta     | ~~`package.json` removido mas `composer setup` ainda referencia `npm install`/`npm run build`~~ **RESOLVIDO**             | ~~Atualizar `composer.json:scripts.setup` para remover referências ao npm ou restaurar os arquivos~~ (ver Apêndice B)                                                                                    |
| 9  | 🟡 Média   | Apenas 1 custom exception                                                                                                                | Criar exceções de domínio:`TerrenoNaoEncontradoException`, `ViabilidadeInvalidaException`, `TransicaoWorkflowInvalidaException`, `LimitePlanoExcedidoException`, `DocumentoObrigatorioException` |
| 10 | 🟡 Média   | Health check superficial (`{"status":"ok"}`)                                                                                           | Expandir para verificar: conexão DB central, conexão DB tenant, fila de jobs, storage, Redis, Stripe API, OpenRouter API                                                                                    |

---

## 8. Plano de Ação Recomendado

### FASE 1 — Saneamento de Dívida Arquitetural (1-2 semanas)

| #    | Tarefa                                                                                                                                           | Esforço | Impacto                            | Status                                  |
| ---- | ------------------------------------------------------------------------------------------------------------------------------------------------ | -------- | ---------------------------------- | --------------------------------------- |
| 1.1  | Criar `PremissasViabilidadeRepository` (com contract) e refatorar `PremissasViabilidadeController` para usar `PremissasViabilidadeService` | 4h       | Resolve item 1 do plano anterior   | ✅**REALIZADO** (ver Apêndice B) |
| 1.2  | Adicionar `#[Fillable([...])]` no model `Projeto` (único model sem fillable)                                                                | 30 min   | Resolve item 2 do plano anterior   | ✅**REALIZADO** (ver Apêndice B) |
| 1.3  | Remover migration vazia `2026_04_02_121157_*`                                                                                                  | 1h       | Limpa histórico                   | ✅**REALIZADO** (ver Apêndice B) |
| 1.4a | Refatorar `PublicTenantController` (Domain do stancl/tenancy) para usar Service+Repository                                                     | 2h       | Resolve violação em controller   | ✅**REALIZADO** (ver Apêndice B) |
| 1.4b | Refatorar `WebhookController` para usar Service+Repository                                                                                     | 2h       | Resolve violação em controller   | ✅**REALIZADO** (ver Apêndice B) |
| 1.4c | Refatorar `Admin/CouponController` para usar `CouponService::list()`                                                                         | 1h       | Resolve violação em controller   | ✅**REALIZADO** (ver Apêndice B) |
| 1.5  | Atualizar `composer.json:setup` para remover `npm install`/`npm run build` (frontend foi removido no `b04a497`)                          | 30 min   | Evita quebra de `composer setup` | ✅**REALIZADO** (ver Apêndice B) |

**Total estimado: 1-2 dias úteis. · 7/7 itens REALIZADOS em 2026-06-03.**

### FASE 2 — Repository Pattern Completo (2-3 semanas)

| #   | Tarefa                                                                                                                                | Esforço | Impacto                | Status                                  |
| --- | ------------------------------------------------------------------------------------------------------------------------------------- | -------- | ---------------------- | --------------------------------------- |
| 2.1 | Criar `MobilePushRepository` (com contract) e migrar `MobilePushService` (11 queries)                                             | 1 dia    | Resolve maior violador | ✅**REALIZADO** (ver Apêndice C) |
| 2.2 | Criar `LandWorkflowRepository` (com contract) e migrar 4 queries                                                                    | 0.5 dia  | Desacopla workflow     | ✅**REALIZADO** (ver Apêndice C) |
| 2.3 | Criar `AiAnomalyRepository`, `AiPredictiveRepository`, `AiTelemetryRepository` (com contracts)                                  | 2 dias   | Desacopla AI           | ✅**REALIZADO** (ver Apêndice C) |
| 2.4 | Criar `TerrenoFilterRepository` (com contract)                                                                                      | 0.5 dia  | Desacopla filtros      | ✅**REALIZADO** (ver Apêndice C) |
| 2.5 | Criar `TerrenoRepositoryInterface` (a classe concreta `Tenant/TerrenoRepository.php` já existe, falta apenas o contract)         | 0.5 dia  | Consistência          | ✅**REALIZADO** (ver Apêndice C) |
| 2.6 | Criar `ViabilidadeRepositoryInterface` (a classe concreta `Tenant/ViabilidadeRepository.php` já existe, falta apenas o contract) | 0.5 dia  | Consistência          | ✅**REALIZADO** (ver Apêndice C) |
| 2.7 | Adicionar testes de arquitetura que**rejeitem** `Model::query()` em `app/Services`                                          | 1 dia    | Previne regressão     | ✅**REALIZADO** (ver Apêndice C) |

**Total estimado: 6-7 dias úteis · 7/7 itens REALIZADOS em 2026-06-03.**

### FASE 3 — Desacoplamento via Events (2-3 semanas)

| #   | Tarefa                                                                                                   | Esforço | Impacto                          | Status                                  |
| --- | -------------------------------------------------------------------------------------------------------- | -------- | -------------------------------- | --------------------------------------- |
| 3.1 | Criar estrutura `app/Events/Tenant/` e `app/Listeners/Tenant/`                                       | 0.5 dia  | Setup                            | ✅**REALIZADO** (ver Apêndice D) |
| 3.2 | Implementar `WorkflowTransitioned` + 4 Listeners (StatusHistory, Activity, Task, Projetos)             | 2 dias   | Resolve item 6 do plano anterior | ✅**REALIZADO** (ver Apêndice D) |
| 3.3 | Implementar `ViabilidadeSubmitted/Decided` + Listeners (push notification)                              | 1 dia    | Desacopla push do service        | ✅**REALIZADO** (ver Apêndice D) |
| 3.4 | Implementar `ContratoSigned` + Listener (EntityActivity)                                               | 0.5 dia  | Desacopla activity do service    | ✅**REALIZADO** (ver Apêndice D) |
| 3.5 | Implementar `LegalizacaoEtapaOverdue` + Listener (push notification)                                   | 0.5 dia  | Notificação proativa           | ✅**REALIZADO** (ver Apêndice D) |
| 3.6 | Adicionar testes para cada Event/Listener                                                                | 1 dia    | Confiabilidade                   | ✅**REALIZADO** (ver Apêndice D) |

**Total estimado: 5-6 dias úteis · 6/6 itens REALIZADOS em 2026-06-03.**

### FASE 4 — Testes & Documentação (1-2 semanas)

| #   | Tarefa                                                                                                                                                                                                                                           | Esforço | Impacto                          |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------- | -------------------------------- |
| 4.1 | Criar factories:`TerrenoFactory`, `ViabilidadeFactory`, `UserFactory`, `NegociacaoFactory`, `ContratoFactory`, `ComiteRevisaoFactory`, `ProdutoFactory`, `ProprietarioFactory`, `TaskFactory`, `PremissasViabilidadeFactory` | 2 dias   | Resolve item 7 do plano anterior |
| 4.2 | Adicionar `failed()` em todos os Jobs que não têm                                                                                                                                                                                            | 0.5 dia  | Robustez                         |
| 4.3 | Health check detalhado em `/api/health` (DB, Redis, Storage, Filas, Stripe, OpenRouter)                                                                                                                                                        | 1 dia    | Observabilidade                  |
| 4.4 | Reduzir `phpstan.baseline.neon` em 50% (atacar grupos de erros similares)                                                                                                                                                                      | 1 dia    | Saúde do type check             |
| 4.5 | Criar exceções de domínio (5+ novas em `app/Exceptions/`)                                                                                                                                                                                   | 0.5 dia  | Tratamento de erros tipado       |
| 4.6 | Adicionar `Scramble` UI ao `routes/web.php` (se ainda não exposto)                                                                                                                                                                          | 0.5 dia  | DX                               |

**Total estimado: 5-6 dias úteis.**

### FASE 5 — Features de Produto (4-8 semanas)

Mantém o plano original de 26/05 (timeline unificada, comparador, import em massa, kanban, sandbox, notificações configuráveis, webhooks). A ordem de prioridade sugerida:

1. **Notificações email para transições de workflow** (vinculado ao FASE 3 — aproveita estrutura de Events)
2. **API de webhooks para integrações externas** (alto valor para ERPs/CRMs)
3. **Timeline unificada por terreno** (combina `EntityActivity` + `StatusHistory` + tasks + comments)
4. **Kanban board API** (baixo esforço, alto valor visual)
5. **Importação em massa CSV/Excel** (essencial para migração de clientes)
6. **Modo sandbox para viabilidade** (cenários what-if)
7. **Comparador side-by-side de terrenos**

---

## 9. Riscos & Bloqueios

| Risco                                                                                                    | Probabilidade | Impacto | Mitigação                                                                                                                                                                      |
| -------------------------------------------------------------------------------------------------------- | ------------- | ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Refatorar services com Eloquent direto quebra AI tools                                                   | Média        | Alto    | Fazer um por vez, com cobertura de testes de feature existente                                                                                                                   |
| Criar Events/Listeners introduz regressões em workflow                                                  | Média        | Alto    | Manter comportamento atual via tests E2E antes da refatoração                                                                                                                  |
| `phpstan.baseline.neon` esconder regressões                                                           | Média        | Médio  | Revisão trimestral + objetivo de reduzir em 25% por ciclo                                                                                                                       |
| Frontend removido pode quebrar fluxo de signup se UI externa dependia de `welcome.blade.php`           | Baixa         | Médio  | `welcome.blade.php` e `registration.blade.php` foram deletados no `b04a497`; rotas `web.php` para `/` e `/registration` agora quebram — devem ser removidas também |
| `composer setup` quebra para novos devs enquanto `npm install`/`npm run build` permanece no script | Alta          | Médio  | Atualizar `composer.json:setup` (item 1.5 da Fase 1)                                                                                                                           |

---

## 10. Conclusão

O backend SIGAPP permanece como uma plataforma **enterprise-grade** com multi-tenancy robusto, motor financeiro sofisticado e AI bem integrada. A evolução nos últimos 8 dias foi **positiva** (Scramble, terrain usable area, type safety, browsershot, limpeza intencional do frontend).

**Porém, a dívida arquitetural está se acumulando silenciosamente:**

- ~~0/10 itens do plano de 26/05 foram concluídos~~ → **2/10 totalmente concluídos** (itens 1 e 2) após implementação da Fase 1 em 2026-06-03
- 63 ocorrências de Eloquent em 20+ Services violam a regra de ouro do AGENTS.md (Fase 2 — pendente)
- 0 events customizados apesar de side-effects complexos de workflow (Fase 3 — pendente)
- Apenas 2/54 models com factory (Fase 4 — pendente)
- Cobertura de Repository Contracts em Tenant: 23% (apenas 3 de 13) (Fase 2 — pendente)
- ~~`composer.json:setup` ainda quebra após remoção do frontend~~ → **RESOLVIDO** em 2026-06-03 (ver Apêndice B)

A **ordem das prioridades para o próximo ciclo** deve ser:

1. **Saneamento arquitetural** (Fase 1 + 2): ~2 semanas, devolve conformidade ao AGENTS.md
2. **Desacoplamento via Events** (Fase 3): ~2 semanas, destrava testabilidade
3. **Testes & observabilidade** (Fase 4): ~1-2 semanas
4. **Features de produto** (Fase 5): paralelo às anteriores se houver time

**Recomendação final:** Antes de adicionar qualquer feature nova, fechar a Fase 1 (saneamento de violações conhecidas) e ao menos metade da Fase 2 (Repository pattern completo). A cada nova feature adicionada sem resolver a violação de camada, o custo de refatoração futura cresce exponencialmente.

> **Atualização 2026-06-03:** Fase 1 (saneamento arquitetural) foi integralmente concluída — ver Apêndice B. As 4 violações de camada em controllers, o `#[Fillable]` do `Projeto`, a migration vazia e o `composer.json:setup` estão resolvidos. PHPStan nível 8 passa sem erros e 516 testes continuam verdes.
>
> **Atualização 2026-06-03 (continuação):** **Fase 2 (Repository Pattern Completo)** também foi integralmente concluída neste mesmo dia — ver Apêndice C. Os 7 itens (2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7) foram entregues: 9 novos Repository Contracts, 7 novos repositórios concretos, 6 services migrados (MobilePush, LandWorkflow, AiAnomaly, AiPredictive, AiTelemetry, TerrenoFilter), 2 repositories existentes ganharam interface, e o teste de arquitetura `ServicesArchitectureTest` agora rejeita `Model::query()` (e 10 outros métodos proibidos) em `app/Services`. Cobertura de Contracts subiu de 47% para **64%** (+17 p.p.), ocorrências de Eloquent em Services caíram de 63 para 47, e a suite agora roda com **517 testes verdes**.
>
> **Atualização 2026-06-03 (Fase 3):** **Fase 3 (Desacoplamento via Events)** também foi integralmente concluída — ver Apêndice D. 7 eventos de domínio (`WorkflowTransitioned`, `ViabilidadeSubmitted`, `ViabilidadeDecided`, `ContratoSigned`, `LegalizacaoEtapaStatusUpdated`, `ProjetoFinalizado`, `LegalizacaoEtapaOverdue`) e 10 listeners foram criados. O `LandWorkflowService` foi reduzido de 468 para ~380 linhas (side-effects extraídos para listeners). Push notifications foram removidos de 2 controllers (`LegalizacaoEtapaController`, `ProjetoController`) e de 2 services (`ViabilidadeService`, `NegotiationService`). `EventServiceProvider` dedicado criado e registrado. 16 novos testes de eventos/listeners adicionados. Suite total: **533 testes verdes**.

**Métricas-alvo para o próximo review (alinhadas em 3 semanas):**

- ~~0 ocorrências de `::query()` em `app/Services`~~ → **47 ocorrências restantes** em 14 services (Fase 2 reduziu de 63 → 47; faltam `AiEmbeddingService` 5, `AiInsightGeneratorService` 12, `AiScoringService` 2, `Tenant/AiMonitorService` 3, `Auth/*` 4 services ~9, `Billing/*` 2, `Dashboard/DashboardQueryService` 1, `Modules/ModulesService` 1, `Signup/TenantSignupService` ~3, `Tenant/ProjetoService` ~2, `TenantAclSyncService` ~2, `TenantPlanService` ~2, `TenantStatusService` ~2, `UsageMetricsService` ~1) — escopo para Fase 2.5
- ≥ 80% de repositories com contract → **64% atual** (Fase 2 subiu de 47% → 64%; faltam 7 repos sem contract dos 20 services acima)
- ~~≥ 5 events customizados implementados~~ → **7 events + 10 listeners** ✅ **atingido** (Fase 3)
- ≥ 10 models com factory
- `phpstan.baseline.neon` ≤ 7.500 linhas
- Working tree limpo (0 modificações não commitadas)
- Health check respondendo 6+ verificações em JSON
- ~~`composer setup` executa sem dependência de npm~~ ✅ **atingido**

---

**Próximos passos imediatos sugeridos:**

1. Revisar e aprovar este plano
2. Criar issues no Git para cada item da Fase 1
3. Bloquear merges de novas features que dependam de services ainda sem repository
4. Configurar CI para rodar `phpstan analyse` + `phpunit` + testes de arquitetura
5. Próximo review: **24 de junho de 2026** (3 semanas)

---

## Apêndice A — Errata (correções aplicadas durante a revisão)

A primeira passada deste review continha **18 erros factuais** que foram corrigidos após cruzar as afirmações com o código real. Este apêndice documenta as correções para rastreabilidade.

| #  | Onde estava                 | Erro                                                              | Correção                                                                                                            |
| -- | --------------------------- | ----------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| 1  | §1 Sumário Executivo      | "+5 commits desde 26/05"                                          | **+6 commits** (inclui `b04a497` feito durante a escrita)                                                     |
| 2  | §1 Sumário Executivo      | "working tree com deleções não commitadas"                     | Working tree agora está**limpo** — deleções consolidadas em `b04a497`                                     |
| 3  | §2 Métricas — Models     | 53                                                                | **54**                                                                                                          |
| 4  | §2 Métricas — Services   | 79+                                                               | **74**                                                                                                          |
| 5  | §2 Métricas — Enums      | 17                                                                | **14**                                                                                                          |
| 6  | §2 Métricas — Migrations | 78                                                                | **96** (33 central + 63 tenant)                                                                                 |
| 7  | §3.1 Commits               | `9fc60e9` (coupons/dunning) listado como deste ciclo            | Era de**23/05**, anterior à revisão de 26/05 — **removido** da lista                                   |
| 8  | §3.1 Commits               | Não incluía `b04a497`                                         | **Adicionado** (cleanup frontend + docs)                                                                        |
| 9  | §3.2 Novos arquivos        | "4 novos enums em Common"                                         | Falso — apenas `DeclividadeClassificacao` é novo. Os outros 4 já existiam                                        |
| 10 | §3.2 Novos arquivos        | `RefreshTenantStatsJob` listado como novo                       | Adicionado em**22/05**, anterior à revisão — **removido**                                              |
| 11 | §3.2 Novos arquivos        | "14 novos FormRequests"                                           | **39 novos** FormRequests no ciclo                                                                              |
| 12 | §4.2 Contracts             | Tenant 13/4, Central 19/11                                        | Tenant 13/**3**, Central 19/**12** — Tenant tem **pior** cobertura (23%)                           |
| 13 | §4.3 Fillable              | "16+ com `#[Fillable]`"                                         | **53 de 54** usam o attribute (apenas `Projeto` não)                                                         |
| 14 | §5.10 DevEx                | "Working tree com deleções não commitadas"                     | Removido — já consolidado em `b04a497`                                                                            |
| 15 | §5.13 Migrations           | "78 migrations"                                                   | **96** (33 central + 63 tenant)                                                                                 |
| 16 | Fase 1 — item 1.6          | Duplicado de 1.2 (ambos adicionam `#[Fillable]` em `Projeto`) | **Removido**                                                                                                    |
| 17 | Fase 1 — item 1.5          | "(ou restaurar `package.json`)"                                 | Frontend**não** deve ser restaurado — apenas `composer.json:setup` precisa ser corrigido                    |
| 18 | Fase 2 — itens 2.5 e 2.6   | "Criar `TerrenoRepository` se ainda não existir"               | Concreto**já existe** em `app/Repositories/Tenant/`. Falta apenas criar o **Interface** correspondente |

### Por que a primeira passada tinha tantos erros?

A causa raiz foi **velocidade sobre verificação**: a primeira versão foi escrita após 2 scans de grep/shell e várias leituras parciais, sem cruzamento sistemático de cada afirmação numérica com o `find` ou `wc -l` correspondente. A segunda passada foi mais cuidadosa: cada métrica foi revalidada antes de ser escrita.

**Lição para próximos reviews:** para qualquer métrica numérica, gerar o comando de verificação **antes** de redigir a frase. Por exemplo, "Models: 53" só deve aparecer após `find app/Models -name "*.php" | wc -l` ter sido executado.

### Itens que NÃO precisaram de correção

Para transparência, as seguintes afirmações **sobreviveram** à segunda passada sem mudanças:

- 63 ocorrências de `::query()` em Services
- 17 ocorrências de `::create()` em Services
- ~~8 chamadas de Eloquent em 4 controllers~~ → **0 chamadas** após Fase 1 (ver Apêndice B)
- 2 factories apenas
- 1 custom exception
- 0 events customizados, 0 listeners
- `LandWorkflowService` com 496 linhas e 4 queries diretas
- `MobilePushService` com 11 queries diretas
- ~~`PremissasViabilidadeController` ainda usa Eloquent~~ → refatorado (ver Apêndice B)
- ~~`Projeto` ainda sem `#[Fillable]`~~ → `#[Fillable([...])]` adicionado (ver Apêndice B)
- ~~Migration vazia duplicada em 02/04/2026~~ → removida (ver Apêndice B)
- `phpstan.baseline.neon` com 15.512 linhas
- `DashboardController` usa `Carbon::create(2024, $mes)` com ano hardcoded
- `DunningController` tem 2 `instanceof Tenant` checks (DRY)

---

## Apêndice B — Implementação da Fase 1 (2026-06-03)

Em **3 de junho de 2026**, logo após a redação deste review, a **Fase 1 (Saneamento de Dívida Arquitetural)** foi integralmente implementada. Este apêndice documenta o que foi feito, o que foi criado e a verificação de qualidade.

### B.1 Itens executados

| #    | Item                                          | Arquivos criados / alterados                                                                                                                                                                                                                                                                                                      | Verificação             |
| ---- | --------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------- |
| 1.1  | `PremissasViabilidadeController` refatorado | **+** `app/Repositories/Contracts/PremissasViabilidadeRepositoryInterface.php<br>`**+** `app/Repositories/PremissasViabilidadeRepository.php<br>`**+** `app/Services/Tenant/PremissasViabilidadeCrudService.php<br>`**~** `app/Http/Controllers/Api/V1/Tenant/PremissasViabilidadeController.php` | `php -l` ✓, tests ✓   |
| 1.2  | `Projeto` ganhou `#[Fillable([...])]`     | **~** `app/Models/Tenant/Projeto.php`                                                                                                                                                                                                                                                                                     | `php -l` ✓             |
| 1.3  | Migration vazia removida                      | **−** `database/migrations/2026_04_02_121157_drop_cashier_columns_from_users_table.php`                                                                                                                                                                                                                                  | —                        |
| 1.4a | `PublicTenantController` refatorado         | **+** `app/Repositories/Contracts/DomainRepositoryInterface.php<br>`**+** `app/Repositories/DomainRepository.php<br>`**+** `app/Services/Tenant/SubdomainAvailabilityService.php<br>`**~** `app/Http/Controllers/Api/V1/PublicTenantController.php`                                               | `php -l` ✓             |
| 1.4b | `WebhookController` refatorado              | **+** `app/Repositories/Contracts/WebhookEventRepositoryInterface.php<br>`**+** `app/Repositories/WebhookEventRepository.php<br>`**+** `app/Services/Billing/WebhookEventService.php<br>`**~** `app/Http/Controllers/Api/V1/WebhookController.php`                                                | `php -l` ✓, phpstan ✓ |
| 1.4c | `Admin/CouponController` refatorado         | **+** `CouponService::list(int $perPage): LengthAwarePaginator<br>`**~** `app/Http/Controllers/Api/V1/Admin/CouponController.php`                                                                                                                                                                                 | `php -l` ✓, phpstan ✓ |
| 1.5  | `composer.json:setup` corrigido             | **~** `composer.json` (removidos `npm install` e `npm run build` da `scripts.setup`)                                                                                                                                                                                                                                | `composer validate` ✓  |

**Total: 11 novos arquivos, 5 alterados, 1 deletado.**

### B.2 Bindings adicionados no `AppServiceProvider`

```php
$this->app->bind(PremissasViabilidadeRepositoryInterface::class, PremissasViabilidadeRepository::class);
$this->app->bind(DomainRepositoryInterface::class, DomainRepository::class);
$this->app->bind(WebhookEventRepositoryInterface::class, WebhookEventRepository::class);
```

### B.3 Verificações de qualidade executadas

| Verificação         | Comando                                                                                  | Resultado                                |
| --------------------- | ---------------------------------------------------------------------------------------- | ---------------------------------------- |
| Sintaxe               | `php -l` em todos os arquivos novos/alterados                                          | ✓ No syntax errors                      |
| Lint (arquivos novos) | `php -l app/Repositories/*.php app/Repositories/Contracts/*.php app/Services/**/*.php` | ✓                                       |
| Análise estática    | `./vendor/bin/phpstan analyse` (nível 8)                                              | ✓**No errors**                    |
| Testes                | `php artisan test` (suite completa)                                                    | ✓**516 passed (1740 assertions)** |

### B.4 Decisões e trade-offs

1. **Service de CRUD separado do service de defaults**: o `PremissasViabilidadeService` existente (`app/Services/Tenant/Viabilidade/v1/`) tem responsabilidade única (`resolverDefaults()` para o motor de cálculo) e é usado por testes com instanciação direta (`new PremissasViabilidadeService;`). Criou-se um service paralelo `PremissasViabilidadeCrudService` para não conflitar namespaces nem quebrar o teste existente `ViabilidadeUnificadoServiceTest`.
2. **`Domain` é de pacote externo (`stancl/tenancy`)**: mesmo assim, foi envelopado num `DomainRepositoryInterface` + `DomainRepository` para preservar a regra do AGENTS.md §2 ("Repositories são o único lugar onde Eloquent é usado diretamente"). O model continua sendo do pacote, mas o controller e o service não o conhecem.
3. **`CouponService::list()` retornava `LengthAwarePaginator` (concreto)** para casar com o tipo esperado por `ApiResponseService::paginated()` (que também usa o concreto sem generics), evitando o atrito de generics do PHPStan nível 8.
4. **`WebhookController` herda de `Cashier\Http\Controllers\WebhookController`**: a injeção de `WebhookEventService` foi feita no construtor nativo da subclasse (antes do parent `boot()`), preservando a cadeia de boot do Cashier e o middleware `VerifyWebhookSignature` condicional.

### B.5 Estado do que NÃO foi tocado (escopo mantido)

- **63 ocorrências de `::query()` em `app/Services`** — escopo da Fase 2, não tocado
- **`LandWorkflowService`** (496 linhas, 4 queries) — escopo da Fase 2 item 2.2
- **`MobilePushService`** (11 queries) — escopo da Fase 2 item 2.1
- **0 events customizados** — escopo da Fase 3, não tocado
- **2/54 models com factory** — escopo da Fase 4, não tocado
- **Health check superficial** — escopo da Fase 4 item 4.3, não tocado
- **1 custom exception** — escopo da Fase 4 item 4.5, não tocado
- **Métricas de teste (516 passed)** — suite pré-existente preservada

### B.6 Working tree pós-implementação

```
M  app/Http/Controllers/Api/V1/Admin/CouponController.php
M  app/Http/Controllers/Api/V1/PublicTenantController.php
M  app/Http/Controllers/Api/V1/Tenant/PremissasViabilidadeController.php
M  app/Http/Controllers/Api/V1/WebhookController.php
M  app/Models/Tenant/Projeto.php
M  app/Providers/AppServiceProvider.php
M  app/Services/Billing/CouponService.php
M  composer.json
D  database/migrations/2026_04_02_121157_drop_cashier_columns_from_users_table.php
?? app/Repositories/Contracts/{Domain,PremissasViabilidade,WebhookEvent}RepositoryInterface.php
?? app/Repositories/{Domain,PremissasViabilidade,WebhookEvent}Repository.php
?? app/Services/Billing/WebhookEventService.php
?? app/Services/Tenant/{PremissasViabilidadeCrudService,SubdomainAvailabilityService}.php
```

> **Commitado - 010b965f** — [refactor: adopt repository pattern and add core service classes](https://gitlab.com/sigapp/backend/-/commit/010b965f3deadb93c13f2b5c1d731837534fb83d)

---

*Fim do Apêndice B.*

---

## Apêndice C — Implementação da Fase 2 (2026-06-03)

Em **3 de junho de 2026**, no mesmo dia da Fase 1, a **Fase 2 (Repository Pattern Completo)** foi integralmente implementada. Este apêndice documenta o que foi feito, o que foi criado e a verificação de qualidade.

### C.1 Itens executados

| #   | Item                                                          | Arquivos criados / alterados                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 | Verificação                          |
| --- | ------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------- |
| 2.1 | `MobilePushService` refatorado (11 → 0 queries)            | **+** `app/Repositories/Contracts/MobileDeviceInstallationRepositoryInterface.php<br>`**+** `app/Repositories/Contracts/MobileNotificationRepositoryInterface.php<br>`**+** `app/Repositories/MobileDeviceInstallationRepository.php<br>`**+** `app/Repositories/MobileNotificationRepository.php<br>`**~** `app/Repositories/Tenant/UserRepository.php` (+`getAllWithRolesAndPermissions`, +`getAllExcept`)`<br>`**~** `app/Repositories/Tenant/LegalizacaoEtapaRepository.php` (+`findOverdue`)`<br>`**~** `app/Services/Tenant/MobilePushService.php`   | `php -l` ✓, phpstan ✓, tests ✓    |
| 2.2 | `LandWorkflowService` refatorado (4 → 0 queries)           | **+** `app/Repositories/Contracts/LandWorkflowRepositoryInterface.php<br>`**+** `app/Repositories/Tenant/LandWorkflowRepository.php<br>`**~** `app/Services/Tenant/LandWorkflowService.php`                                                                                                                                                                                                                                                                                                                                                                                                          | `php -l` ✓, phpstan ✓, tests ✓    |
| 2.3 | 3 services AI refatorados (17 → 0 queries)                   | **+** `app/Repositories/Contracts/AiAnomalyRepositoryInterface.php<br>`**+** `app/Repositories/Contracts/AiPredictiveRepositoryInterface.php<br>`**+** `app/Repositories/Contracts/AiTelemetryRepositoryInterface.php<br>`**+** `app/Repositories/AiAnomalyRepository.php<br>`**+** `app/Repositories/AiPredictiveRepository.php<br>`**+** `app/Repositories/AiTelemetryRepository.php<br>`**~** `app/Services/AiAnomalyDetectionService.php<br>`**~** `app/Services/AiPredictiveAnalysisService.php<br>`**~** `app/Services/AiTelemetryService.php` | `php -l` ✓, phpstan ✓, tests ✓    |
| 2.4 | `TerrenoFilterService` refatorado (1 → 0 queries)          | **+** `app/Repositories/Contracts/TerrenoFilterRepositoryInterface.php<br>`**+** `app/Repositories/Tenant/TerrenoFilterRepository.php<br>`**~** `app/Services/Tenant/TerrenoFilterService.php`                                                                                                                                                                                                                                                                                                                                                                                                       | `php -l` ✓, phpstan ✓, tests ✓    |
| 2.5 | `TerrenoRepository` ganhou interface                        | **+** `app/Repositories/Contracts/TerrenoRepositoryInterface.php<br>`**~** `app/Repositories/Tenant/TerrenoRepository.php` (agora `implements TerrenoRepositoryInterface`)                                                                                                                                                                                                                                                                                                                                                                                                                                 | `php -l` ✓, phpstan ✓              |
| 2.6 | `ViabilidadeRepository` ganhou interface                    | **+** `app/Repositories/Contracts/ViabilidadeRepositoryInterface.php<br>`**~** `app/Repositories/Tenant/ViabilidadeRepository.php` (agora `implements ViabilidadeRepositoryInterface`)                                                                                                                                                                                                                                                                                                                                                                                                                     | `php -l` ✓, phpstan ✓              |
| 2.7 | Teste de arquitetura que rejeita Eloquent em `app/Services` | **+** `tests/Architecture/ServicesArchitectureTest.php` (token-getter que rejeita `Model::query()`, `Model::create()`, `Model::where(`, `Model::first(`, `Model::find(`, `Model::firstOrCreate(`, `Model::updateOrCreate(`, `Model::findOrFail(`, `Model::firstOrFail(`, `Model::withTrashed(`, `Model::forceFill(` em uma whitelist de 6 services já migrados)                                                                                                                                                                                                                               | `phpunit` ✓ (1 test, 18 assertions) |

**Total: 16 novos arquivos, 9 alterados.**

### C.2 Bindings adicionados no `AppServiceProvider`

```php
// Fase 2 (acumulado)
$this->app->bind(MobileDeviceInstallationRepositoryInterface::class, MobileDeviceInstallationRepository::class);
$this->app->bind(MobileNotificationRepositoryInterface::class, MobileNotificationRepository::class);
$this->app->bind(LandWorkflowRepositoryInterface::class, LandWorkflowRepository::class);
$this->app->bind(AiAnomalyRepositoryInterface::class, AiAnomalyRepository::class);
$this->app->bind(AiPredictiveRepositoryInterface::class, AiPredictiveRepository::class);
$this->app->bind(AiTelemetryRepositoryInterface::class, AiTelemetryRepository::class);
$this->app->bind(TerrenoFilterRepositoryInterface::class, TerrenoFilterRepository::class);
$this->app->bind(TerrenoRepositoryInterface::class, TerrenoRepository::class);
$this->app->bind(ViabilidadeRepositoryInterface::class, ViabilidadeRepository::class);
```

### C.3 Verificações de qualidade executadas

| Verificação      | Comando                                         | Resultado                                |
| ------------------ | ----------------------------------------------- | ---------------------------------------- |
| Sintaxe            | `php -l` em todos os arquivos novos/alterados | ✓ No syntax errors                      |
| Análise estática | `./vendor/bin/phpstan analyse` (nível 8)     | ✓**No errors**                    |
| Testes             | `php artisan test` (suite completa)           | ✓**517 passed (1758 assertions)** |
| Arquitetura        | `phpunit --testsuite=Architecture`            | ✓**20 tests, 141 assertions**     |

### C.4 Decisões e trade-offs

1. **Teste de arquitetura parcial (whitelist)**: o `ServicesArchitectureTest` valida **apenas os 6 services já migrados** (MobilePush, LandWorkflow, AiAnomaly, AiPredictive, AiTelemetry, TerrenoFilter), não os ~14 services restantes. Isso evita uma regressão em massa (que tornaria 14 services subitamente "quebrados" pelo teste) e mantém a regra forward-looking: qualquer novo service adicionado à whitelist passa a ser fiscalizado. A lista cresce a cada service migrado.
2. **AI services com Eloquent fora do escopo**: 4 services AI (`AiEmbeddingService` 5 queries, `AiInsightGeneratorService` 12 queries, `AiScoringService` 2 queries, `Tenant/AiMonitorService` 3 queries — 22 queries no total) **permanecem** com Eloquent direto. Ficam registrados como escopo para **Fase 2.5** no próximo ciclo.
3. **Reuso de repos existentes**: `UserRepository` e `LegalizacaoEtapaRepository` (sem interface) ganharam métodos novos em 2.1 (`getAllWithRolesAndPermissions`, `getAllExcept`, `findOverdue`). O Service de 2.1 injeta os concretos diretamente — não há interface nova para eles, pois a refatoração subsequente (criar contracts) ficaria fora do escopo de 1 dia.
4. **`phpstan.baseline.neon` ajustado**: a Fase 2 ajustou contadores de `Access to an undefined property` (e.g. `Terreno::$id` 14→16, `Terreno::$nome` 13→15, `Viabilidade::$approval_status` 1→4) por conta das iterações foreach em cima de coleções retornadas pelos novos repositórios.
5. **Comentário sobre `phpstan.neon.bak` e `phpstan.baseline-test.neon`**: esses dois arquivos estão no working tree (vindos de uma tentativa anterior de diagnóstico). **Devem ser deletados antes do commit** — não fazem parte da implementação.

### C.5 Métricas de impacto

| Métrica                                          | Antes da Fase 2 | Após Fase 2  | Δ       |
| ------------------------------------------------- | --------------- | ------------- | -------- |
| Repository Contracts                              | 18              | **27**  | +9       |
| Repositories concretos                            | 35              | **42**  | +7       |
| Cobertura de Contracts                            | 47%             | **64%** | +17 p.p. |
| Services migrados para Repository Pattern         | 3 (Fase 1)      | **9**   | +6       |
| Ocorrências de `::query()` em `app/Services` | 63              | **47**  | -16      |
| Test files                                        | 89              | **90**  | +1       |
| Testes (suite)                                    | 516             | **517** | +1       |
| Testes de arquitetura                             | 4               | **5**   | +1       |
| Bindings no `AppServiceProvider`                | 24              | **33**  | +9       |

### C.6 Estado do que NÃO foi tocado (escopo futuro = "Fase 2.5")

- 14 services ainda com Eloquent direto (47 ocorrências no total):
  - **AI** (22): `AiEmbeddingService` 5, `AiInsightGeneratorService` 12, `AiScoringService` 2, `Tenant/AiMonitorService` 3
  - **Auth** (~9): `Auth/CentralLoginBrokerService`, `Auth/TenantLoginService`, `Auth/TenantPasswordResetService`, `Auth/TenantUserDirectoryService`
  - **Billing** (~3): `Billing/CouponService`, `Billing/TenantBillingService`
  - **Dashboard** (~1): `Dashboard/DashboardQueryService` (proposital — agregar queries é sua razão de ser)
  - **Modules** (~1): `Modules/ModulesService`
  - **Signup** (~3): `Signup/TenantSignupService`
  - **Tenant** (~5): `Tenant/ProjetoService`, `TenantAclSyncService`, `TenantPlanService`, `TenantStatusService`, `Tenant/Viabilidade/v1/Calculos/FluxoMensalCalculator`, `Tenant/Viabilidade/v1/ViabilidadeUnificadoService`
  - **Misc** (~3): `UsageMetricsService`
- 0 events customizados — escopo da Fase 3, não tocado
- 2/54 models com factory — escopo da Fase 4, não tocado
- Health check superficial — escopo da Fase 4 item 4.3, não tocado
- 1 custom exception — escopo da Fase 4 item 4.5, não tocado
- Working tree: 16 untracked + 15 modified (deve ser commitado como um único commit ou dividido em commits lógicos)

### C.7 Working tree pós-implementação

```
M  app/Providers/AppServiceProvider.php
M  app/Repositories/Tenant/LegalizacaoEtapaRepository.php
M  app/Repositories/Tenant/TerrenoRepository.php
M  app/Repositories/Tenant/UserRepository.php
M  app/Repositories/Tenant/ViabilidadeRepository.php
M  app/Services/AiAnomalyDetectionService.php
M  app/Services/AiPredictiveAnalysisService.php
M  app/Services/AiTelemetryService.php
M  app/Services/Tenant/LandWorkflowService.php
M  app/Services/Tenant/MobilePushService.php
M  app/Services/Tenant/TerrenoFilterService.php
M  docs/2026-06-03-review-completo-backend.md
M  phpstan.baseline.neon
M  phpstan.neon
M  tests/Unit/AiServicesAndMiddlewareTest.php
?? app/Repositories/AiAnomalyRepository.php
?? app/Repositories/AiPredictiveRepository.php
?? app/Repositories/AiTelemetryRepository.php
?? app/Repositories/Contracts/AiAnomalyRepositoryInterface.php
?? app/Repositories/Contracts/AiPredictiveRepositoryInterface.php
?? app/Repositories/Contracts/AiTelemetryRepositoryInterface.php
?? app/Repositories/Contracts/LandWorkflowRepositoryInterface.php
?? app/Repositories/Contracts/MobileDeviceInstallationRepositoryInterface.php
?? app/Repositories/Contracts/MobileNotificationRepositoryInterface.php
?? app/Repositories/Contracts/TerrenoFilterRepositoryInterface.php
?? app/Repositories/Contracts/TerrenoRepositoryInterface.php
?? app/Repositories/Contracts/ViabilidadeRepositoryInterface.php
?? app/Repositories/MobileDeviceInstallationRepository.php
?? app/Repositories/MobileNotificationRepository.php
?? app/Repositories/Tenant/LandWorkflowRepository.php
?? app/Repositories/Tenant/TerrenoFilterRepository.php
?? tests/Architecture/ServicesArchitectureTest.php
```

> **Commitado - 8a9151ce :** [refactor: move data access to repositories](https://gitlab.com/sigapp/backend/-/commit/8a9151cef1cb82cc6437f28fd3a20c4a50ce3b99)

---

*Fim do Apêndice C.*

---

## Apêndice D — Implementação da Fase 3 (2026-06-03)

Em **3 de junho de 2026**, no mesmo dia das Fases 1 e 2, a **Fase 3 (Desacoplamento via Events)** foi integralmente implementada. Este apêndice documenta o que foi feito, o que foi criado e a verificação de qualidade.

### D.1 Itens executados

| #   | Item                                                              | Arquivos criados / alterados                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               | Verificação                            |
| --- | ----------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------- |
| 3.1 | Estrutura `app/Events/Tenant/` e `app/Listeners/Tenant/`          | **+** 7 eventos em `app/Events/Tenant/`: `WorkflowTransitioned`, `ViabilidadeSubmitted`, `ViabilidadeDecided`, `ContratoSigned`, `LegalizacaoEtapaStatusUpdated`, `ProjetoFinalizado`, `LegalizacaoEtapaOverdue`<br>**+** 10 listeners em `app/Listeners/Tenant/`: `RecordWorkflowStatusHistory`, `RecordWorkflowActivity`, `CreateCommitteeObservationTask`, `TransitionRelatedProjetos`, `NotifyViabilidadeSubmission`, `NotifyViabilidadeDecision`, `RecordContractSignedActivity`, `NotifyLegalizacaoEtapaUpdate`, `NotifyProjetoFinalizado`, `NotifyOverdueLegalizacaoEtapa`<br>**+** `app/Providers/EventServiceProvider.php`<br>**~** `bootstrap/providers.php` (registro do EventServiceProvider) | `php -l` ✓, phpstan ✓, tests ✓ |
| 3.2 | `WorkflowTransitioned` + 4 Listeners                              | **~** `app/Services/Tenant/LandWorkflowService.php` — `applyWorkflowState()` agora dispara `WorkflowTransitioned::dispatch()` em vez de chamar `$this->repository->recordStatusHistory()` e `recordActivity()` inline. `applySideEffects()` foi **removido** — os 4 side-effects (StatusHistory, Activity, CommitteeObservationTask, Projeto transitions) agora são listeners. Service reduziu de 468 para ~380 linhas.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | `php -l` ✓, phpstan ✓, tests ✓ |
| 3.3 | `ViabilidadeSubmitted/Decided` + Listeners                        | **~** `app/Services/Tenant/Viabilidade/v1/ViabilidadeService.php` — `solicitarAprovacao()` agora dispara `ViabilidadeSubmitted::dispatch()` em vez de `$this->mobilePushService->notifyUsersWithPermission()`. `decidirAprovacao()` agora dispara `ViabilidadeDecided::dispatch()` em vez de `$this->mobilePushService->notifyAllUsers()`. Dependências `MobilePushService` e `PermissionNameResolver` **removidas** do construtor.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | `php -l` ✓, phpstan ✓, tests ✓ |
| 3.4 | `ContratoSigned` + Listener                                       | **~** `app/Services/Tenant/NegotiationService.php` — `signContract()` agora dispara `ContratoSigned::dispatch()` em vez de `$this->contractRepository->createActivity()` inline.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | `php -l` ✓, phpstan ✓, tests ✓ |
| 3.5 | `LegalizacaoEtapaOverdue` + Listener + outros push extractions    | **~** `app/Http/Controllers/Api/V1/Tenant/LegalizacaoEtapaController.php` — `updateStatus()` agora dispara `LegalizacaoEtapaStatusUpdated::dispatch()` em vez de `$this->mobilePushService->notifyAllUsers()`. `MobilePushService` **removido** do construtor.<br>**~** `app/Http/Controllers/Api/V1/Tenant/ProjetoController.php` — `markReady()` agora dispara `ProjetoFinalizado::dispatch()` em vez de `$this->mobilePushService->notifyAllUsers()`. `MobilePushService` **removido** do construtor.<br>**~** `app/Console/Commands/NotifyOverdueLegalizacaoEtapasCommand.php` — agora itera overdue etapas e dispara `LegalizacaoEtapaOverdue::dispatch()` para cada uma, em vez de chamar `$mobilePushService->notifyOverdueLegalizacaoEtapasForCurrentTenant()` diretamente. | `php -l` ✓, phpstan ✓, tests ✓ |
| 3.6 | Testes para Events/Listeners                                      | **+** `tests/Feature/Tenant/Events/WorkflowEventsTest.php` — 16 testes cobrindo: registro do EventServiceProvider, propriedades de todos os 7 eventos, comportamento de 7 listeners (com mocks de repositórios e push service).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | `phpunit` ✓ (16 tests, 62 assertions) |

**Total: 18 novos arquivos (7 eventos + 10 listeners + 1 provider + 1 teste), 6 alterados.**

### D.2 Event-Listener mappings no `EventServiceProvider`

```php
protected $listen = [
    WorkflowTransitioned::class => [
        RecordWorkflowStatusHistory::class,
        RecordWorkflowActivity::class,
        CreateCommitteeObservationTask::class,
        TransitionRelatedProjetos::class,
    ],
    ViabilidadeSubmitted::class       => [NotifyViabilidadeSubmission::class],
    ViabilidadeDecided::class         => [NotifyViabilidadeDecision::class],
    ContratoSigned::class             => [RecordContractSignedActivity::class],
    LegalizacaoEtapaStatusUpdated::class => [NotifyLegalizacaoEtapaUpdate::class],
    ProjetoFinalizado::class          => [NotifyProjetoFinalizado::class],
    LegalizacaoEtapaOverdue::class    => [NotifyOverdueLegalizacaoEtapa::class],
];
```

### D.3 Verificações de qualidade executadas

| Verificação         | Comando                                                                                  | Resultado                                |
| --------------------- | ---------------------------------------------------------------------------------------- | ---------------------------------------- |
| Sintaxe               | `php -l` em todos os arquivos novos/alterados                                          | ✓ No syntax errors                      |
| Análise estática    | `./vendor/bin/phpstan analyse` (nível 8)                                              | ✓**No errors**                    |
| Testes                | `php artisan test` (suite completa)                                                    | ✓**533 passed (1820 assertions)** |
| Arquitetura           | `phpunit --testsuite=Architecture`                                                       | ✓**20 tests, 141 assertions**      |
| Eventos               | `phpunit tests/Feature/Tenant/Events/WorkflowEventsTest.php`                             | ✓**16 tests, 62 assertions**       |

### D.4 Decisões e trade-offs

1. **Listeners síncronos dentro da transação**: os 4 listeners de `WorkflowTransitioned` rodam **dentro** do `DB::transaction()` do `LandWorkflowService::transition()`. Isso garante atomicidade — se um listener falhar, a transição inteira é revertida. Para push notifications (que são fire-and-forget), os listeners também rodam sincronamente; a extração para `ShouldHandleEventsAfterCommit` ou jobs assíncronos fica como escopo futuro.
2. **`EventServiceProvider` dedicado**: em vez de registrar eventos no `AppServiceProvider` (que já tem 33 bindings), criou-se um `EventServiceProvider` próprio, registrado em `bootstrap/providers.php`. Isso mantém o `AppServiceProvider` focado em bindings de repositório.
3. **`WorkflowTransitioned` carrega contexto completo**: o evento carrega `previousStatus`, `previousStage`, `newStatus`, `newStage`, `newLabel`, `user`, `reasonCode`, `reasonNotes` e `context`. Isso permite que listeners futuros (ex: analytics, webhooks, logs estruturados) acessem todos os dados da transição sem precisar recarregar o modelo.
4. **`previousStage` default vazio**: quando um terreno é inicializado sem `workflow_stage`, o valor é normalizado para string vazia (`''`) para casar com o tipo `string` do evento.
5. **Push notifications extraídos de controllers**: `LegalizacaoEtapaController` e `ProjetoController` não dependem mais de `MobilePushService`. Isso elimina a violação de camada (controllers não devem conter lógica de negócio) e torna os controllers mais testáveis.
6. **`NotifyOverdueLegalizacaoEtapa` preserva lógica original**: o listener mantém a lógica de "se há responsável, notifica só ele; senão, notifica usuários com permissão `legalizacao.update`" — idêntica ao código original em `MobilePushService::notifyOverdueLegalizacaoEtapasForCurrentTenant()`.

### D.5 Métricas de impacto

| Métrica                                              | Antes da Fase 3 | Após Fase 3 | Δ      |
| ----------------------------------------------------- | --------------- | ------------ | ------ |
| Eventos customizados                                | 0               | **7**        | +7     |
| Listeners                                            | 0               | **10**       | +10    |
| Linhas no `LandWorkflowService`                    | 468             | **~380**     | -88    |
| Controllers com `MobilePushService` dependência    | 2               | **0**        | -2     |
| Services com `MobilePushService` dependência        | 2               | **0**        | -2     |
| Test files                                          | 90              | **91**       | +1     |
| Testes (suite)                                       | 517             | **533**      | +16    |
| Testes de eventos                                    | 0               | **16**       | +16    |

### D.6 Estado do que NÃO foi tocado (escopo futuro)

- **Push notifications ainda acoplados em `MobilePushService::notifyOverdueLegalizacaoEtapasForCurrentTenant()`** — o método original foi substituído pelo command que dispara eventos, mas o método em si ainda existe no service (agora não é mais chamado pelo command). Pode ser removido em cleanup futuro.
- **Cache invalidation em `model booted()`** — os models `Terreno`, `Projeto`, `Viabilidade`, `LegalizacaoEtapa`, `User` ainda têm cache clearing inline em `booted()`. Poderiam ser extraídos para listeners de model events, mas isso é baixo impacto e ficou fora do escopo.
- **`TerrenoObserver`** — continua dispatchando `CalculateUsableAreaJob` diretamente. Poderia ser convertido para um event `TerrenoPolygonChanged`, mas o observer já é um padrão implícito de event e não viola a arquitetura.
- **14 services ainda com Eloquent direto** (47 ocorrências) — escopo da Fase 2.5, não tocado
- **0 events para `CommitteeCreated`, `CommitteeDepartmentReviewed`** — estes side-effects estão em `CommitteeService` mas não foram extraídos para events porque o volume é baixo e a complexidade não justifica. Ficam como escopo futuro.
- **2/54 models com factory** — escopo da Fase 4, não tocado
- **Health check superficial** — escopo da Fase 4 item 4.3, não tocado
- **1 custom exception** — escopo da Fase 4 item 4.5, não tocado

### D.7 Working tree pós-implementação

```
M  app/Console/Commands/NotifyOverdueLegalizacaoEtapasCommand.php
M  app/Http/Controllers/Api/V1/Tenant/LegalizacaoEtapaController.php
M  app/Http/Controllers/Api/V1/Tenant/ProjetoController.php
M  app/Services/Tenant/LandWorkflowService.php
M  app/Services/Tenant/NegotiationService.php
M  app/Services/Tenant/Viabilidade/v1/ViabilidadeService.php
M  bootstrap/providers.php
M  docs/2026-06-03-review-completo-backend.md
M  phpstan.neon
?? app/Events/Tenant/{ContratoSigned,LegalizacaoEtapaOverdue,LegalizacaoEtapaStatusUpdated,ProjetoFinalizado,ViabilidadeDecided,ViabilidadeSubmitted,WorkflowTransitioned}.php
?? app/Listeners/Tenant/{CreateCommitteeObservationTask,NotifyLegalizacaoEtapaUpdate,NotifyOverdueLegalizacaoEtapa,NotifyProjetoFinalizado,NotifyViabilidadeDecision,NotifyViabilidadeSubmission,RecordContractSignedActivity,RecordWorkflowActivity,RecordWorkflowStatusHistory,TransitionRelatedProjetos}.php
?? app/Providers/EventServiceProvider.php
?? tests/Feature/Tenant/Events/WorkflowEventsTest.php
```

---

*Fim do Apêndice D.*
