# Análise Completa do Backend + TODO para Frontend

> **Data original:** 04/06/2026  
> **Última atualização:** 09/08/2026  
> **Projeto:** SIGAPP — SaaS multi-tenant de gestão imobiliária (incorporadoras/loteadoras)  
> **Backend:** Laravel 13 · PHP 8.4+ · PostgreSQL (schema por tenant) · Sanctum · Cashier/Stripe  
> **Fonte canônica de regras:** `AGENTS.md` e `tests/Architecture/`

Este documento é um **mapa de contrato** para o frontend (Next.js irmão) e para IAs de implementação. Rotas e features mudam; em caso de conflito, o código em `routes/` e os testes de arquitetura vencem.

---

## Sumário

1. [Visão Geral da Arquitetura](#1-visão-geral-da-arquitetura)
2. [Entidades/Recursos Principais](#2-entidadesrecursos-principais)
3. [Endpoints da API](#3-endpoints-da-api)
4. [Fluxos de Autenticação](#4-fluxos-de-autenticação)
5. [Padrões de Resposta e Formato](#5-padrões-de-resposta-e-formato)
6. [Feature Flags e Controle de Acesso](#6-feature-flags-e-controle-de-acesso)
7. [TODO para Frontend](#7-todo-para-frontend)
8. [Changelog desta revisão](#8-changelog-desta-revisão)

---

## 1. Visão Geral da Arquitetura

### Stack do Backend

| Item | Valor atual |
|---|---|
| Framework | Laravel 13 (`laravel/framework ^13.0`) |
| Linguagem | PHP **8.4+** |
| Banco | **PostgreSQL** central + **1 schema por tenant** (`tenant_{slug}`), com **pgvector** |
| Multi-tenancy | `stancl/tenancy` + manager custom `PostgreSQLSchemaPublicManager` |
| Identificação | Subdomínio `{tenant}.sigapp.com.br` + header `X-Tenant` (fallback local/testing em host central) |
| Auth | Laravel Sanctum (Bearer) + broker de login central com transfer tickets |
| Admin central | Sanctum + **MFA TOTP** (abilities `admin` + `admin:mfa`) |
| RBAC | `spatie/laravel-permission` (`teams => false`) + templates por plano |
| Billing | Laravel Cashier (Stripe) — planos, entitlements, **add-ons**, cupons, dunning |
| IA | Laravel AI SDK (`laravel/ai ^0.10`) — agente SIG_IA; análise de PDF via OpenCode Go |
| E-mail | Resend |
| PDF / Excel | `spatie/laravel-pdf` + Browsershot; `maatwebsite/excel` |
| Docs API | Scramble em `/docs/api` (alias `/docs`) |
| Storage | Local em dev; documentos/relatórios/exports no disk **`s3`** em prod |
| Testes | PHPUnit 13 (Architecture / Unit / Feature) — **não** Pest |

### Estrutura Multi-Tenant

```
DOMÍNIOS CENTRAIS (CENTRAL_DOMAINS / config/tenancy.php)
  ├── api.sigapp.com.br / hosts centrais exatos
  └── admin (quando no mesmo host central, protegido por central.admin + MFA)

CADA TENANT → {slug}.sigapp.com.br
  ├── schema PostgreSQL próprio (não é database separado)
  ├── cache tag-base tenant-aware
  └── storage local sufixado por tenant; S3 com isolamento de path/quota
```

**Importante:**

- Subdomínios reservados (`app`, `admin`, `www`, serviços DNS em `TENANCY_RESERVED_SUBDOMAINS`) não podem ser tenant.
- Host desconhecido sob `APP_DOMAIN`: navegação `GET/HEAD` (fora de `/api`) redireciona para `FRONTEND_URL`; APIs/métodos mutáveis → `404 TENANT_NOT_FOUND`.
- Rotas de negócio do tenant exigem assinatura ativa **e** perfil fiscal completo (`tenant.billing-profile.complete` → `428 TENANT_BILLING_PROFILE_INCOMPLETE` se pendente). Auth, bootstrap (`/start`) e regularização de billing **não** são bloqueados.

### Separação de Responsabilidades

```
Central (routes/api.php, domínio central)
  ├── Landing/Blog/Planos públicos
  ├── Signup + Stripe Checkout
  ├── Demo request (lead público)
  ├── Consent log (LGPD cookies)
  ├── Auth broker (login → tenants → ticket)
  ├── Admin central (tenants, planos, entitlements, add-ons, cupons, MFA, anúncios)
  └── Webhooks Stripe

Tenant (routes/tenant.php → routes/tenant/*)
  ├── Auth direto + exchange-ticket + password reset
  ├── Bootstrap /start (features, limits, modules, RBAC, billing_profile resumo)
  ├── Prospecção, viabilidade, comitê, negociação, legalização, projetos
  ├── Documentos + inteligência documental
  ├── Workspace (busca, views salvas, tasks, shortlists, reports builder)
  ├── IA (chat SSE, scoring, relatórios, contextual)
  ├── Billing (plano, add-ons, histórico, dunning, perfil fiscal)
  └── Mobile (push + captura offline)
```

### Organização das rotas tenant

Arquivos em `routes/tenant/` (carregados **uma vez** pelo agregador):

| Arquivo | Domínio |
|---|---|
| `public-auth.php` | Login, ticket, password reset (público) |
| `account-billing.php` | Auth me, locale, start/modules, billing, add-ons, perfil fiscal, anúncios |
| `workspace-admin.php` | Usage, exports async, search, reports, tasks, shortlists, onboarding, tenant-admin |
| `prospection.php` | Terrenos, imports, documentos, corretores, regionais, produtos, proprietários |
| `viability-ai.php` | Viabilidades, cenários, premissas, SIG IA |
| `projects-committee.php` | Projetos, planejamento, comitê, reuniões |
| `negotiation.php` | Negociações, contratos, deal room |
| `platform-legal.php` | Cidades, dashboard, mobile, legalizações |

---

## 2. Entidades/Recursos Principais

### Tenant (schema do cliente)

| Entidade | Descrição | Módulo |
|---|---|---|
| `Terreno` | Entidade central do workflow | Prospecção |
| `Viabilidade` | Estudo financeiro (motor v3, snapshot canônico) | Viabilidade |
| `ViabilidadeScenario` | Cenário what-if sem alterar a base | Viabilidade |
| `PremissasViabilidade` | Premissas com vigência e versionamento | Configurações |
| `Legalizacao` / `LegalizacaoEtapa` | Processo + Gantt com dependências | Legal |
| `Negociacao` / `NegociacaoEvento` | Negociação e histórico | Negociação |
| `DealOffer` / aprovações / condições | Deal room (ofertas ≠ assinatura de contrato) | Negociação |
| `Contrato` / `ContratoParte` | Contrato e partes | Negociação |
| `ComiteRevisao` + pareceres/pendências | Revisão de comitê | Comitê |
| `ComiteMeeting*` | Sessões/reuniões + ata | Comitê |
| `ComiteAiDossier` | Dossiê assistido por IA | Comitê |
| `Projeto` + milestones/deps/risks | Projeto e planejamento | Projetos |
| `Produto` / `TerrenoProduto` | Catálogo e associação ao terreno | Config / Prospecção |
| `Proprietario` / `CorretorExterno` / `Regional` | Cadastros de apoio | Prospecção / Config |
| `Documento` (+ versões, analyses, reviews) | Anexos + inteligência documental | Dados |
| `Task` / `EntityActivity` / `StatusHistory` | Colaboração e timeline | Workspace / Prospecção |
| `Shortlist` / `ShortlistItem` | Comparação de 2–4 terrenos | Prospecção |
| `User` (Tenant) | Usuário com roles Spatie + departamento | Admin |
| `Department` | Departamento | Admin |
| `Role` / `Permission` | RBAC | Admin |
| `AiGeneratedReport` / `AiReportGeneration` | Relatórios PDF de terreno (sync/async) | IA |
| `ReportTemplate` / `ReportRun` / `ReportSchedule` | Construtor de relatórios | Reports |
| `SavedView` | Visões salvas de listas | Workspace |
| `MobileDeviceInstallation` / `MobileCapture*` | Push + captura mobile | Mobile |
| `TenantExport` | Export assíncrono genérico | Exports |
| `UserOnboarding*` | Onboarding por perfil (eventos allowlisted) | Onboarding |

### Central

| Entidade | Descrição |
|---|---|
| `Tenant` | Cliente (status, plano, `scheduled_plan`, perfil fiscal, storage alerts) |
| `Plan` / `Entitlement` / `TenantEntitlement` | Catálogo e overrides |
| `BillingAddon` / `TenantAddonSubscription` / `TenantAddonPurchase` | Add-ons Stripe |
| `AiCreditTransaction` | Ledger de créditos avulsos de IA |
| `Coupon` / `WebhookEvent` / `Dispute` | Billing e idempotência de webhooks |
| `User` (SIGAPP) | Admin da plataforma (TOTP criptografado) |
| `Post` / `PlatformAnnouncement` | Blog e anúncios de plataforma |
| `AuditLog` / `ConsentLog` / `DemoRequest` | Auditoria, LGPD, leads |
| `Cidade` | Base territorial IBGE |

### Removido / não reintroduzir

- **`Position` (cargos/funções)**: removido do schema tenant. Não há rotas `tenant-admin/positions`.

---

## 3. Endpoints da API

Prefixo versionado: **`/api/v1`**.  
Documentação viva: **`/docs/api`**.

### 3.1 CENTRAL — domínio central

#### Públicos

| Método | Rota | Descrição | Rate limit |
|---|---|---|---|
| `GET` | `/tenant/subdomain-availability/{subdomain}` | Disponibilidade de slug | `api-public` |
| `GET` | `/health` | Health mínimo versionado | `api-public` |
| `GET` | `/plans` · `/plans/{slug}` | Catálogo de planos | `api-public` |
| `POST` | `/signup` | Cria tenant + Checkout Stripe | `api-public` |
| `GET` | `/signup/{sessionId}/status` | Status do checkout | `signup-status` |
| `POST` | `/demo-request` | Lead de demonstração | `demo-request` |
| `POST` | `/consent-log` | Consentimento cookies (append-only) | `consent-log` |
| `POST` | `/webhook/stripe` | Webhooks Stripe (**sem** throttle/CSRF) | — |
| `POST` | `/auth/login` | Broker: descobre tenants do e-mail | `central-login` |
| `POST` | `/auth/select-tenant` | Emite transfer ticket (IP amarrado à sessão) | `central-login` |
| `POST` | `/auth/password/forgot` · `/reset` | Reset de senha (tenant) via host central | password-reset-* |
| `GET` | `/blog` · `/blog/categories` · `/blog/{slug}` | Blog institucional | `api-public` |
| `POST` | `/admin/login` | Admin: só senha → desafio MFA | `admin-login` |
| `POST` | `/admin/login/verify` | Admin: TOTP/recovery → token `admin`+`admin:mfa` | `admin-mfa` |

Health legado: `GET /api/health` e `GET /up` (framework). Detalhes (pgvector etc.): `GET /api/v1/health/details` (admin autenticado).

#### Admin central (Sanctum + `central.admin` + MFA)

| Área | Rotas principais |
|---|---|
| Sessão | `PUT /locale`, `POST /auth/logout|logout-all|refresh`, `GET /auth/me` |
| MFA | `GET /admin/mfa`, `POST /admin/mfa/rotate`, `/rotate/verify`, `/recovery-codes` |
| Dashboard | `GET /admin/dashboard`, `GET /tenant-status` |
| Blog | `apiResource /admin/posts` |
| Tenants | `GET /admin/tenants`, show, activate, suspend |
| Planos do tenant | assign / upgrade / downgrade + entitlements extras |
| Add-ons do tenant | `GET /admin/tenants/{tenant}/addons`, `POST .../reconcile`, `GET .../access-matrix` |
| Catálogo | plans, entitlements, **billing-addons**, coupons |
| ACL | `GET /admin/acl/catalog`, `GET /admin/acl/plans/{planId}/role-matrix` |
| Usuários / auditoria | users, audit-logs, login-attempts |
| Anúncios | CRUD + `POST /admin/announcements/{id}/send` |

### 3.2 TENANT — `{slug}.sigapp.com.br`

#### Públicos

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/auth/login` | Login direto no tenant |
| `POST` | `/auth/exchange-ticket` | Troca ticket do broker por token Sanctum |
| `POST` | `/auth/password/forgot` · `/reset` | Reset de senha no host do tenant |

#### Autenticados (sem exigir perfil fiscal completo)

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/auth/logout` · `/logout-all` · `/refresh` | Sessão |
| `GET/PUT` | `/auth/me` | Perfil (roles, permissões, departamento — **sem** position) |
| `PUT` | `/locale` | Idioma (`pt-br` / `en-us`) |
| `GET` | `/start` | Bootstrap oficial: `access.features`, `access.limits`, `access.modules`, reasons, `tenant.billing_profile` (resumo) |
| `GET` | `/modules` | Contrato legado de módulos |
| `GET/PUT` | `/me/notification-preferences` · settings | Preferências de e-mail/digest |
| `GET/PATCH` | `/me/preferences` | Preferências de UI |
| `GET/POST` | `/me/onboarding/*` | Onboarding (feature `onboarding.profile`) |
| `GET` | `/platform-announcements/active` · dismiss | Anúncios de plataforma |
| `GET/PUT` | `/tenant/billing-profile` | Perfil fiscal completo (**ADMIN**) |
| Billing admin | subscription, portal, swap, setup-intent, payment-method, coupon, dunning, history, invoices | **ADMIN** |
| Add-ons | `GET /tenant/addons`, `/mine`, `POST /purchase`, `PATCH /{addon}`, `POST /{addon}/cancel` | **ADMIN** |
| `GET` | `/tenant` · `/tenant/usage` · `/tenant/plans` | Dados e uso |

#### Dashboard (`check.feature:dashboard.*`)

| Método | Rota | Feature extra |
|---|---|---|
| `GET` | `/dashboard/overview` | `dashboard.overview` |
| `GET` | `/dashboard/management-overview` | `dashboard.management` |
| `GET` | `/dashboard/cards`, `cadastros-mensais`, `terrenos-responsavel`, `top-cidades`, `resumo`, `anos-disponiveis`, `area-opcao-detalhe`, `cadastros-mensais-responsavel` | base |
| `GET` | `/dashboard/status-chart` | `dashboard.funnel` |
| `GET` | `/dashboard/vgv-anual` | `dashboard.vgv` |
| `GET` | `/dashboard/unidades-fechadas-anual` | `dashboard.units_closed` |

#### Terrenos (`prospection`)

| Método | Rota | Notas |
|---|---|---|
| `GET/POST/PUT/DELETE` | `/terrenos` | Create com `enforce.limits:terrenos` + gate prospection |
| `GET` | `/terrenos/filter` · `/select` · `/pipeline` | Pipeline: feature `prospection.pipeline_board` |
| `POST` | `/terrenos/compare` | Comparação pontual |
| `GET/POST` | `/terrenos/{id}/informacoes` · update/delete info | Notas |
| `GET` | `/terrenos/{id}/workflow` · `workflow-state` · `readiness` | Transições + bloqueios |
| `POST` | `/terrenos/{id}/workflow` | Transição |
| `PUT` | `/terrenos/{id}/qualificacao` | Qualificação |
| `POST` | `/terrenos/{id}/import-kmz` · `recalculate-area` | Geo / área útil async |
| `GET` | `/terrenos/{id}/timeline` | Timeline |
| Imports cadastrais | `/terrenos/imports/template`, `POST /`, show, rows, confirm, errors | Excel async (fila `exports`) |
| Polígonos lote | `POST /terrenos/polygon-imports`, polygons list/link/delete | KMZ/KML até 10 arqs |
| Exports síncronos legados | PDF/Excel lista, PDF detalhe, checklist, viabilidade | Migrar para `/exports` quando possível |

**Workflow (status_code):**

```
em_analise → aguardando_viabilidade → viabilidade_aprovada →
aguardando_comite → negociacao_minuta → contrato_assinado →
legalizando → legalizado_finalizado
(+ descartado, arquivado)
```

#### Viabilidades (`viabilities.enabled`)

| Método | Rota | Notas |
|---|---|---|
| CRUD | `/viabilidades` | Create/update recalculam motor |
| `GET` | `/viabilidades/for-select` · `terreno/{id}` · `latest` | |
| `GET` | `/viabilidades/modelos-financiamento` | Catálogo UI dos perfis |
| `POST` | `/viabilidades/compare` | |
| Aprovação | solicitar / aprovar / reprovar / **revogar-aprovacao** | throttle `viabilidade-approval` |
| `POST` | ativar · duplicate · gerar-dre · recalcular · restore | |
| Cenários | `/viabilidades/{id}/scenarios` (+ calculate, promote) | feature `viabilities.scenarios` |
| Premissas | `/premissas-viabilidade` (+ `/{id}/historico`) | gate configurations |

**Perfis de financiamento (selecionáveis):** `proprio`, `apoio_producao`, `plano_empresario`, `alocacao_recursos`.  
`cef` é **alias histórico** de `apoio_producao` (aceito em legado, fora do catálogo UI).

**Resposta de cálculo:** passa por `ViabilidadeResultProjector` — seções `summary`, `kpis`, `dre`, `cash_flow`, `comercial`, `premises`, `charts` respeitam entitlements; seção pedida em `include` sem plano → `403 PLAN_FEATURE_DISABLED`.

Estudos `em_aprovacao` / `aprovada` / `rejeitada` / `revogada` são **imutáveis**; recálculo de decidido gera nova versão `pendente`.

#### Legalizações (`legalizations`)

CRUD + etapas + reorder + status + sync-gantt + recalcular-progresso.  
Insights (feature `legalization.control_center`): control-center, critical-path, costs.

#### Comitê (`committee`)

Lista/create/show, department-reviews, decision, pendências.  
Dossiê IA: show / regenerate / export-pdf.  
Reuniões (features `committee.meeting` / `committee.meeting_mode`): sessions, agenda, attendance, minutes, approve minutes.  
Fechar sessão **não** inventa decisão para pauta pendente.

#### Negociação / Contratos (`negotiation`)

CRUD negociações + events; CRUD contratos + sign.  
Deal room (`negotiation.deal_room`): offers accept/reject, approvals, conditions. **Aceitar oferta ≠ assinar contrato.**

#### Projetos

| Feature | Rotas |
|---|---|
| `projects.enabled` | CRUD parcial + eligible-terrenos + marcar-pronto-registro + cancelar |
| `projects.planning` | milestones (reorder), dependencies (anti-ciclo), risks |

Alias legado `projects_room` / `projects.room` resolve para o catálogo atual — **não** usar como chave comercial nova.

#### Documentos

CRUD + tipos/categorias + view/download. Upload: multipart, máx. **10 MB**, conta `storage_gb`.  
Inteligência (`documents.intelligence`): requirements, versions, analysis (async PDF), human review. MVP analisa **somente PDF**. Chat SIG IA **não** faz upload — referencia documento por ID.

#### Workspace / Reports / Exports

| Feature | Superfície |
|---|---|
| `search.global` | `GET /search` |
| `workspace.saved_views` | CRUD saved-views + set-default |
| `collaboration.inbox` | activities |
| `collaboration.tasks` | tasks (+ comments, my-queue) |
| `prospection.comparison` | shortlists + items |
| `reports.builder` | catalog, templates, runs, schedules, download |
| exports async | `POST /exports` → 202; `GET /exports/{id}`; download autenticado |
| `onboarding.profile` | me/onboarding |

#### Cadastros auxiliares

- Corretores externos, proprietários, terreno-produtos  
- Regionais (`regionals`), produtos (`product_settings` + histórico)  
- Cidades (`territorial_base`) + SIDRA município  

#### Admin do tenant (`tenant.admin`)

| Recurso | Rotas |
|---|---|
| Users | CRUD + send-invite + module-permissions (`enforce.limits:users` no create) |
| Roles / Permissions / Departments | CRUD + selects |
| ~~Positions~~ | **removido** |

#### IA (`ai`)

| Rota | Notas |
|---|---|
| `GET /ai/conversations` · messages | Participante polimórfico (laravel/ai 0.10) |
| `GET /ai/budget` | Orçamento tenant |
| `POST /ai/sig-ai` | Chat SSE — **leitura/recomendação/PDF**; não muta workflow |
| Relatório terreno | `POST .../relatorio-pdf` (sync legado) + `.../jobs` async 202 + status + download reports |
| Scoring / predictive / automation monitor | Mantidos |
| `POST /ai/context` · apply recommendation | feature `ai.contextual` |

Middlewares obrigatórios em chat/provider: `ai.rate_limit` + `ai.budget`.

#### Mobile

| Feature | Rotas |
|---|---|
| base | devices, notifications (read/read-all/unread-count/delete) |
| `mobile.capture` | captures CRUD por `client_id` UUID, attachments multipart, commit, status — conflitos `409` com `base_version` |

---

## 4. Fluxos de Autenticação

### 4.1 Broker central → tenant

```
1. POST /api/v1/auth/login          (domínio central)  { email, password }
2a. 1 tenant  → pode concluir fluxo com ticket/token conforme contrato atual do broker
2b. N tenants → { broker_session_id, tenants[] }
3. POST /auth/select-tenant         { broker_session_id, tenant_id, device_name }
     → { ticket }  (sessão amarrada ao IP do login; claim atômico)
4. POST /api/v1/auth/exchange-ticket (subdomínio do tenant) { ticket, device_name? }
     → { token, user, abilities, expires_at, tenant.billing_profile (resumo) }
```

### 4.2 Login direto no tenant

```
POST /api/v1/auth/login
Body: { email, password, device_name? }
→ token Sanctum + user (roles/permissions/department) + billing_profile resumo
```

### 4.3 Admin central com MFA

```
1. POST /admin/login          { email, password }
   → desafio (primeiro acesso: enroll TOTP em duas etapas)
2. POST /admin/login/verify   { challenge_id, code | recovery_code }
   → token com abilities admin + admin:mfa; sessão MFA ~12h
3. Rotação: POST /admin/mfa/rotate (+ verify) revoga tokens e troca recovery codes
```

Tokens admin **não** renovam por `/auth/refresh` da mesma forma que tenant — tratar sessão admin à parte.

### 4.4 Perfil fiscal obrigatório

- Novos tenants: `billing_profile_required=true` até `PUT /tenant/billing-profile` (ADMIN).
- Bloqueio de módulos de negócio: middleware `tenant.billing-profile.complete` → **428** `TENANT_BILLING_PROFILE_INCOMPLETE`.
- CNPJ: formatos numérico legado **e** alfanumérico RF (14 posições, DV módulo 11 ASCII-48). Nunca `digits()` cego em CNPJ alfanumérico.

### 4.5 Token e senha

| Ação | Rota |
|---|---|
| Refresh | `POST /auth/refresh` |
| Logout | `POST /auth/logout` / `logout-all` |
| Forgot/reset | `/auth/password/forgot` · `/reset` (central ou tenant) |

### 4.6 Rate limiters (principais)

| Limiter | Uso |
|---|---|
| `api-public` | rotas públicas |
| `api-auth` | autenticadas |
| `central-login` / `admin-login` / `admin-mfa` | login |
| `transfer-ticket` | exchange-ticket |
| `password-reset-*` | reset |
| `signup-status` / `consent-log` / `demo-request` | públicos específicos |
| `viabilidade-approval` | fluxo de aprovação |
| `exports` | criação de export async |

### 4.7 Bootstrap — `GET /start`

Fonte oficial da matriz efetiva (plano + add-ons + override + RBAC):

- `access.features` / `access.limits` / `access.modules`
- `reasons` quando indisponível: `module` | `plan` | `rbac`
- resumo seguro `tenant.billing_profile`

Use **`/start`** para menu, feature gates e hard-blocks de UI. `/modules` é legado.

---

## 5. Padrões de Resposta e Formato

### 5.1 Sucesso / criação

```json
{ "success": true, "data": { }, "message": "..." }
```

Mensagens `UPPER_SNAKE_CASE` são chaves i18n (`pt-br.json` / `en-us.json`).

### 5.2 Sem conteúdo

HTTP `204` corpo vazio.

### 5.3 Paginação (formato **nativo** Laravel)

`ApiResponseService::paginated()` retorna o envelope do `JsonResource::collection` — **não** o envelope `success`+`meta` antigo:

```json
{
  "data": [ ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 7,
    "path": "...",
    "per_page": 15,
    "to": 15,
    "total": 100
  }
}
```

O frontend **não** deve assumir `success: true` em listagens paginadas.

### 5.4 Erro

```json
{
  "success": false,
  "error": {
    "code": "SNAKE_CASE_CODE",
    "message": "...",
    "details": { }
  }
}
```

Validação 422 (híbrido por compatibilidade):

```json
{
  "success": false,
  "message": "...",
  "errors": { "campo": ["..."] },
  "error": { "code": "VALIDATION_ERROR", "message": "...", "details": { } }
}
```

### 5.5 Códigos de erro relevantes

| Código | HTTP | Significado |
|---|---|---|
| `UNAUTHORIZED` | 401 | Não autenticado |
| `FORBIDDEN` / `PLAN_FEATURE_DISABLED` | 403 | Sem permissão / feature do plano |
| `NOT_FOUND` / `TENANT_NOT_FOUND` | 404 | Recurso / tenant host |
| `CONFLICT` | 409 | Conflito (ex.: captura mobile `base_version`) |
| `GONE` / sessão broker | 410 | Broker session inválida/expirada |
| `VALIDATION_ERROR` | 422 | Validação |
| `TENANT_BILLING_PROFILE_INCOMPLETE` | **428** | Perfil fiscal pendente |
| `TOO_MANY_REQUESTS` | 429 | Rate limit / budget IA |
| `PROTECTED_ROLE` / `LAST_TENANT_ADMIN` / `CANNOT_DELETE_SELF` | 4xx | Regras RBAC |
| `POLYGON_REQUIRED` | 422 | Geo obrigatório |

---

## 6. Feature Flags e Controle de Acesso

### 6.1 Modelo de autorização (camadas)

1. **Host/tenant** — `EnforceHostAccess` + tenancy  
2. **Auth** — Sanctum + `auth.tenant` / `auth.central`  
3. **Assinatura** — `subscription.active`  
4. **Perfil fiscal** — `tenant.billing-profile.complete`  
5. **Plano/add-ons/override** — `check.feature`, `enforce.limits` via `PlanMatrixService`  
6. **RBAC** — `permission.gate`, FormRequest `authorize()`, policies  

Ordem da matriz de entitlements: **plano base → add-ons ativos/compras → `tenant_entitlements` (override final)**.  
Limites `-1` = ilimitado (não somar). `ai_budget` avulso vive no ledger consumível.

### 6.2 Features principais (catálogo)

**Core / módulos**

| Key | Descrição |
|---|---|
| `prospection` | Terrenos e prospecção |
| `viabilities.enabled` (+ seções summary/dre/cash_flow/kpis/charts/comercial/premises) | Viabilidade |
| `committee` / `negotiation` / `legalizations` | Fluxo pós-viabilidade |
| `projects.enabled` / `projects.planning` | Projetos |
| `product_settings` / `regionals` / `territorial_base` | Cadastros |
| `dashboard.enabled` + overview/funnel/vgv/units_closed/management/executive/goals | Dashboard |
| `ai` / `ai.contextual` | Assistente e IA contextual |
| `exports.pdf` / `exports.excel` | Exportações |
| `home` | Home |

**Roadmap (exemplos)**

| Key | UI sugerida |
|---|---|
| `prospection.pipeline_board` | Board kanban |
| `prospection.comparison` | Shortlists / comparar |
| `prospection.terrain_cockpit` | Cockpit do terreno |
| `collaboration.tasks` / `collaboration.inbox` | Tasks + inbox |
| `viabilities.scenarios` | Cenários what-if |
| `committee.meeting` / `committee.meeting_mode` | Reuniões |
| `negotiation.deal_room` | Deal room |
| `legalization.control_center` | Central de legalização |
| `reports.builder` | Construtor de relatórios |
| `documents.intelligence` | Versões + análise PDF |
| `search.global` / `workspace.saved_views` / `workspace.personalization` | Workspace |
| `mobile.capture` | Captura mobile |
| `onboarding.profile` / `experience.accessibility` / `dashboard.personalization` | UX |

**Limites:** `users`, `terrenos`, `products`, `storage_gb`, `ai_budget`.

Planos comerciais de referência: **Broker → Básico → Master → Pro** (escada no `EntitlementSeeder`).

### 6.3 Middlewares (aliases)

`force.json`, `tenant.logs`, `api.logger`, `central.context`, `tenant.context`, `auth.central`, `auth.tenant`, `enforce.limits`, `subscription.active`, `tenant.billing-profile.complete`, `central.admin`, `tenant.admin`, `user.admin`, `permission.gate`, `check.feature`, `ai.rate_limit`, `ai.budget` + grupo `tenant` (`InitializeTenancyFlexible`).

### 6.4 RBAC

Roles canônicas (`RolesEnum`): `ADMIN`, `DIRECTOR`, `MANAGER`, `SUPERVISOR`, `ANALYST`, `USER`.  
Sempre usar o enum — nunca strings soltas.  
Templates por plano em `database/rbacTemplates/`.  
No módulo `configurations`: templates padrão dão `manager` ao ADMIN, `viewer` a DIRECTOR/MANAGER/SUPERVISOR; Faturamento exclusivo ADMIN.

### 6.5 Módulos de menu (`ModulesEnum`)

Dashboard, Prospecção, Corretores, Viabilidade, Comitê, Negociação, Legal, Projetos, Configurações, Dados, Relatórios, Admin, IA — ordem/setor definidos no enum. O frontend deve preferir a matriz de `GET /start` a hardcode.

---

## 7. TODO para Frontend

> O frontend é repositório **Next.js separado**. CORS via `CORS_ALLOWED_ORIGINS`; URLs `FRONTEND_URL` / `LANDING_URL`.

### 7.1 Stack recomendada

| Tecnologia | Notas |
|---|---|
| Next.js (App Router) | Central + tenant por host/subdomínio |
| TypeScript | Tipos espelhando Resources |
| Tailwind + shadcn/ui | UI base |
| TanStack Query | Cache server-state |
| Zustand | Auth/session/UI |
| React Hook Form + Zod | Forms |
| Recharts | Dashboard |
| Leaflet (ou similar) | Polígonos KMZ |
| date-fns / Intl | Datas e BRL |
| fetch/axios | HTTP + SSE nativo para IA |

### 7.2 Ordem de implementação sugerida

1. **Auth** (broker + direto + admin MFA) + client API + refresh  
2. **`GET /start`** → shell, menu, feature/permission gates  
3. **Perfil fiscal (428)** e gates de assinatura  
4. **Dashboard** + **Terrenos** (CRUD, workflow, mapa, KMZ)  
5. **Viabilidades** (modelos financiamento, seções por entitlement, aprovação)  
6. **Comitê / Negociação / Legal / Projetos**  
7. **Documentos + inteligência**  
8. **Workspace** (search, tasks, shortlists, saved views)  
9. **Reports builder** + **exports async**  
10. **IA** (SSE, budget, relatórios async)  
11. **Billing** (swap, add-ons, dunning, histórico)  
12. **Mobile capture** (se app nativo/PWA)  
13. **Onboarding** e personalização  

### 7.3 Autenticação e autorização — tasks

- [ ] Auth store: token, user, tenant, abilities, billing_profile resumo  
- [ ] Fluxos: login tenant, broker multi-tenant, exchange-ticket no host correto  
- [ ] Admin: login → challenge MFA → verify; rota de enroll/rotate recovery  
- [ ] Interceptor 401 → refresh (tenant); **não** misturar token admin com tenant  
- [ ] Interceptor **428** → forçar wizard de perfil fiscal (ADMIN)  
- [ ] Interceptor 403 `PLAN_FEATURE_DISABLED` / RBAC → empty state de upgrade ou 403 page  
- [ ] Interceptor 429 → toast + backoff (IA e rate limits)  
- [ ] `PermissionGate` + `FeatureGate` baseados em `/start` (`reasons`)  
- [ ] Header opcional `X-Tenant` **somente** em dev/local em host central — nunca em prod como fonte de verdade  

### 7.4 Layout e navegação

- [ ] App shell: sidebar a partir de `access.modules`, header com plano/usage, locale, anúncios  
- [ ] Banner de assinatura past_due / dunning  
- [ ] Banner storage próximo do limite (quando usage expuser)  
- [ ] Mobile: drawer / bottom nav  
- [ ] Deep-links com query de filtros (URL state)  

### 7.5 Telas por módulo

#### Públicas (central)

- [ ] Landing, planos, signup multi-step + polling checkout  
- [ ] Blog  
- [ ] Demo request  
- [ ] Consent cookies → `POST /consent-log`  
- [ ] Login broker + seletor de tenant + forgot/reset password  

#### Admin central

- [ ] Dashboard, tenants (activate/suspend/plan/entitlements/addons/access-matrix)  
- [ ] Plans / entitlements / **billing-addons** / coupons  
- [ ] Posts, users, audit logs, login attempts, announcements  
- [ ] MFA status / rotate / recovery codes  

#### Tenant — core

- [ ] Dashboard overview (+ management se feature) com `force_refresh`  
- [ ] Terrenos: lista, detalhe multi-tab, workflow, readiness, pipeline board, compare/shortlist  
- [ ] Import Excel (template → upload → preview rows → confirm) e polygon-imports  
- [ ] Exports: preferir `POST /exports` (poll status) em volumes grandes  
- [ ] Viabilidades: formulário por seções, seletor de **modelo de financiamento**, projector de seções, cenários, aprovação/revogação  
- [ ] Premissas com histórico e regras de vigência (UI não deixa sobrepor)  
- [ ] Legalização Gantt + control center (critical path, custos)  
- [ ] Comitê + reuniões + dossiê IA PDF  
- [ ] Negociação + deal room (ofertas/aprovações/condições)  
- [ ] Contratos + sign  
- [ ] Projetos + milestones/deps/risks (validar ciclos na UI com feedback da API)  
- [ ] Documentos: upload, preview, versões, análise PDF, revisão humana  
- [ ] Admin tenant: users (invite), roles, permissions, departments — **sem positions**  
- [ ] Regionais, produtos (+ histórico), proprietários, corretores  
- [ ] Billing: plano, scheduled_plan, swap upgrade/downgrade, portal Stripe, cupons, dunning, invoices  
- [ ] Add-ons: catálogo com `price` estruturado, `is_purchasable`, purchase/cancel  
- [ ] Perfil fiscal (CPF/CNPJ alfanumérico, máscara `XX.XXX.XXX/XXXX-XX`)  
- [ ] Reports builder: catalog fechado, templates, runs async, schedules  
- [ ] Search global + saved views  
- [ ] Tasks colaborativas + inbox de activities  
- [ ] IA chat SSE + budget meter + relatório terreno async + scoring cards  
- [ ] Onboarding allowlisted events  
- [ ] Mobile capture sync (se aplicável)  

### 7.6 Componentes reutilizáveis (prioridade)

- [ ] `DataTable` (sort, filter, pagination nativa Laravel)  
- [ ] `StatusBadge` para enums de domínio  
- [ ] `FeatureGate` / `PermissionGate`  
- [ ] `AsyncSelect` (`/select`, `/for-select`)  
- [ ] `FileUpload` com progresso + quota errors  
- [ ] `MapPolygon` + KMZ dropzone  
- [ ] `Gantt` legalização  
- [ ] `SseChat`  
- [ ] `ExportJobPoller` (202 → poll → download)  
- [ ] `BillingProfileWizard`  
- [ ] `PlanMatrix` (comparativo features)  
- [ ] `EmptyPlanUpgrade` (quando `reasons` contém `plan`)  

### 7.7 Estado e queries

| Store | Conteúdo |
|---|---|
| Auth | token, user, abilities |
| Bootstrap | resultado de `/start` (features, limits, modules, billing_profile) |
| UI | sidebar, locale, theme |

Query keys sugeridas: `['start']`, `['terrenos', filters]`, `['viabilidade', id, include]`, `['export', id]`, `['ai', 'budget']`, `['addons']`, etc.  
Remover qualquer cache de `positions`.

### 7.8 Integração API — regras práticas

- Base URL pelo host atual (`https://{host}/api/v1`).  
- `Accept: application/json` sempre (`force.json`).  
- Multipart: **não** setar `Content-Type` manualmente (boundary).  
- Paginação: ler `data` + `meta` + `links`, não `success`.  
- SSE IA: `fetch` + `ReadableStream`; headers `X-Conversation-Id` / provider quando presentes.  
- Jobs 202: poll até `completed|failed`; download autenticado; **nunca** confiar em path S3 cru.  
- Não enviar preço/plano/tenant-id do cliente como fonte de verdade de billing.  
- CNPJ: normalizar separadores **preservando letras**.  

### 7.9 Requisitos técnicos específicos

- [ ] Streaming SIG IA com cancelamento (AbortController)  
- [ ] Polling de imports/exports/report runs/AI report jobs com backoff  
- [ ] Conflito 409 mobile capture → UI de merge/rebase por `base_version`  
- [ ] Deal room: estados de oferta vs contrato claramente separados  
- [ ] Viabilidade: ocultar seções sem entitlement; se `include` forçado e 403, mensagem de plano  
- [ ] Comparação shortlist: 2–4 terrenos, sem “recomendação automática” inventada  
- [ ] Acessibilidade e i18n (`pt-br` / `en-us`) para todas as strings de UI  

### 7.10 Boas práticas

1. Tipos a partir dos Resources/Scramble — não inventar campos.  
2. Feature gating no cliente é UX; o backend sempre revalida.  
3. Skeleton em toda lista; empty states com CTA de plano quando `reasons` = `plan`.  
4. Filtros na URL.  
5. Debounce em search (global e listagens).  
6. Nunca logar tokens ou conteúdos de documentos/PDF de análise.  
7. Preferir `/start` a caches locais de permissões desatualizados após swap de plano.  
8. Após upgrade/downgrade, invalidar `['start']`, usage e billing.  
9. Documentação humana: este arquivo + Scramble; regras de domínio finas no `AGENTS.md`.  

---

## 8. Changelog desta revisão

**2026-08-09** — Renomeado para o padrão `docs/YYYY-MM-DD-*.md` e reescrito contra o código atual:

- PHP 8.4+, PostgreSQL **schema-per-tenant** (não MySQL/DB separado)  
- Remoção de `Position` / cargos  
- MFA admin central, perfil fiscal obrigatório (428), CNPJ alfanumérico  
- Add-ons Stripe, matriz plano→add-on→override, `scheduled_plan`  
- Rotas modularizadas em `routes/tenant/*`  
- Novas superfícies: imports, polygon imports, exports async, shortlists/pipeline, cenários e modelos de financiamento, deal room, reuniões de comitê, dossiê IA, planejamento de projetos, reports builder, inteligência documental, busca/views, onboarding, captura mobile, IA contextual e relatórios async  
- Paginação no formato nativo Laravel  
- Bootstrap oficial `GET /start` com `access.*` e `reasons`  
- Features roadmap alinhadas ao `EntitlementSeeder`  
- TODO frontend reordenado e alinhado aos contratos reais  

---

> **Como usar:** trate este arquivo como checklist e mapa de contrato. Para o detalhe de um endpoint, confira `routes/` + Scramble (`/docs/api`). Para regras de arquitetura e domínio, leia `AGENTS.md`. Comece o frontend por **Auth → `/start` → Shell → Terrenos → Viabilidade**.
