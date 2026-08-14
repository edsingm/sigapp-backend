# AGENTS.md — Backend SIGAPP (Laravel 13 · Multi-Tenant)

Este arquivo é o **hub** das regras obrigatórias para IAs (Cursor, Claude, Copilot, Grok, etc.). Ele descreve o que o repositório **realmente é** — não um template genérico.

Em caso de dúvida, o **código** e os testes de arquitetura (`tests/Architecture/`) são a fonte da verdade.

> **Convenção vs. framework:** o Laravel não impõe Controller → Service → Repository. As regras de camada deste projeto são **convenções verificadas** pelos testes em `tests/Architecture/`.

Detalhes por domínio vivem em **[`.agents/docs/`](.agents/docs/README.md)**. Abra o doc do domínio **antes** de implementar naquela área.

---

## Manutenção

- **Sempre leia este hub** antes de alterar o backend.
- Feature nova ou alteração considerável (arquitetura, módulos, rotas, middlewares, jobs/scheduler, billing, IA, storage, RBAC, env, comandos, deploy, testes) → atualize o **arquivo em `.agents/docs/`** do domínio no mesmo conjunto de mudanças.
- Se o mapa de gatilhos mudar → atualize a tabela abaixo **e** [`.agents/docs/README.md`](.agents/docs/README.md).
- Não documente microfixes sem impacto estrutural. Atualização **cirúrgica**, fiel ao código — não transforme em changelog.

---

## Antes de codar

1. Identifique o contexto: **central** × **tenant**.
2. Leia as **regras sempre on** deste hub.
3. Se a tarefa cair no mapa abaixo, **abra o doc** correspondente.

---

## Mapa sob demanda

| Se você for tocar em… | Leia |
|---|---|
| Stack, comandos, Docker, deploy, env de prod | [`.agents/docs/visao-e-operacao.md`](.agents/docs/visao-e-operacao.md) |
| Central × tenant, host, `X-Tenant`, schemas, cache | [`.agents/docs/multi-tenancy.md`](.agents/docs/multi-tenancy.md) |
| Camadas, pastas, FormRequest, API, Eloquent, i18n, nomes | [`.agents/docs/arquitetura.md`](.agents/docs/arquitetura.md) |
| Login, MFA, RBAC, middlewares, rotas, throttle, health | [`.agents/docs/auth-rbac-rotas.md`](.agents/docs/auth-rbac-rotas.md) |
| Stripe, planos, add-ons, webhooks, entitlements | [`.agents/docs/billing.md`](.agents/docs/billing.md) |
| SIG_IA, tools, RAG, budget, análise documental | [`.agents/docs/ia.md`](.agents/docs/ia.md) |
| Workflow do terreno, módulos de negócio, reports, mobile | [`.agents/docs/dominio-tenant.md`](.agents/docs/dominio-tenant.md) |
| Motor de viabilidade, fórmulas, financiamento, premissas | [`.agents/docs/viabilidade.md`](.agents/docs/viabilidade.md) |
| Jobs, filas, e-mail, uploads, testes, Pint/PHPStan, PR | [`.agents/docs/jobs-qualidade-checklist.md`](.agents/docs/jobs-qualidade-checklist.md) |

---

## Visão em uma página

**SIGAPP** é um SaaS multi-tenant de gestão imobiliária (terrenos → viabilidade → comitê → negociação → contratos → legalização → projetos) com agente de IA (**SIG_IA**). Tenant por subdomínio; admin da plataforma nos domínios centrais.

| Item | Valor |
|---|---|
| **Framework** | Laravel 13 · PHP **8.4+** |
| **Banco** | PostgreSQL **16** + `pgvector` (central + schema por tenant) · SQLite nos testes |
| **Multi-tenancy** | `stancl/tenancy` + `PostgreSQLSchemaPublicManager` · subdomínio / `X-Tenant` |
| **Auth** | Sanctum + broker central (transfer tickets) · MFA TOTP no admin |
| **RBAC** | Spatie Permission · enums `RolesEnum` / `ModulesEnum` |
| **Billing** | Cashier/Stripe · entitlements · add-ons · webhooks idempotentes |
| **IA** | `laravel/ai` · `config/ai.php` · nunca SDK de provider no chat |
| **Filas** | Redis em prod · grupos: provisioning, ai, exports, notifications, default |
| **Testes** | PHPUnit 13 (Architecture, Unit, Feature) · **não** Pest |
| **Qualidade** | Pint (`laravel`) · PHPStan nível **8** |

### Comandos essenciais

```bash
composer setup                      # install + .env + key + migrate
composer dev                        # serve + queue:listen + pail + vite
composer test                       # config:clear + php artisan test
composer analyse                    # phpstan (memory 512M)
./vendor/bin/pint --test            # checa formatação
php artisan test --testsuite=Architecture
php artisan sigapp:release          # deploy: migrate central + tenants (nunca seed)
php artisan sigapp:bootstrap        # só ambiente vazio: migrate + seed
```

---

## Multi-tenancy (resumo)

| | **Central** | **Tenant** |
|---|---|---|
| Rotas | `routes/api.php` (`central_domains`) | `routes/tenant.php` + `routes/tenant/*` |
| Models | `app/Models/Central/` (+ `User`, `AuditLog`, `ConsentLog`) | `app/Models/Tenant/` |
| Controllers | `Api/V1/`, `Api/V1/Admin/` | `Api/V1/Tenant/` |
| Migrations | `database/migrations/` | `database/migrations/tenant/` |
| Usuário | `UserType::SIGAPP` | `UserType::TENANT` |

- Toda rota nova declara `central.context` **ou** `tenant.context`.
- Migration de tenant **sempre** em `database/migrations/tenant/` — errar a pasta quebra o provisionamento.
- Detalhe (host, cache, lifecycle): [`.agents/docs/multi-tenancy.md`](.agents/docs/multi-tenancy.md).

---

## Regras sempre on

### Código e camadas

- PHP **8.4+**, tipos em tudo, enums nativos, `declare(strict_types=1)` em arquivos novos de domínio.
- Formatação só via **Pint**; análise via **PHPStan 8** — não esconda erro novo em baseline/ignore.
- **Controller thin** → **Service** (sem `Request`, sem Eloquent direto nos migrados) → **Repository** (queries).
- Mutação: **FormRequest** com `authorize()` real (`return true;` em tenant **quebra o CI**).
- Use `$request->validated()` — nunca `$request->all()` / `->validate()` inline no controller.
- Models: `$fillable` explícito, `$casts`, factory; nunca `$guarded = []`.
- Side-effects via **Events + Listeners** registrados no `EventServiceProvider`.
- **Não** instale pacotes nem mude a árvore de pastas sem aprovação explícita.
- Prefira recursos nativos do Laravel antes de biblioteca externa.

### API e i18n

- Envelope: `ApiResponseService` + **Resources** — nunca model cru.
- Mensagens `UPPER_SNAKE_CASE` nos **dois** locales: `resources/lang/pt-br.json` e `en-us.json`.
- API versionada `/api/v1/`; rate limiting **nomeado** em toda rota; kebab-case plural.
- Exceções de domínio estendem `DomainException`.

### Auth, RBAC, billing, IA

- Roles/módulos **só** via enums (`RolesEnum`, `ModulesEnum`) — nunca strings mágicas.
- Autorização **antes** do Service (rota / middleware / FormRequest). Services **não** autorizam.
- **IA** só via `laravel/ai` + `config/ai.php`; rotas de IA com `ai.rate_limit` + `ai.budget`.
- Webhook Stripe **somente** em `WebhookController` / `WebhookEventService`; nunca confiar no cliente para preço/plano.
- Transfer ticket, fórmulas de viabilidade e webhooks Stripe são **áreas sensíveis** — leia o doc do domínio antes de alterar.

### Jobs, storage, env

- Todo Job com `failed()`, `$tries`, `$timeout` (e `$backoff` quando couber).
- Uploads/arquivos gerados: MIME/tamanho no FormRequest; quota via `StorageQuotaService::commitFile()`; disk `s3` para documentos/relatórios/exports.
- `.env` nunca commitado; ao criar variável, atualize `.env.example` (e `.env.production.example` se for prod).

### Testes

- Feature: happy path + pelo menos um erro (401/403/422); Unit para lógica.
- Mock de externos: `Http`/`Mail`/`Notification`/`Queue`/`Event` fakes — **nunca** Stripe/Resend/IA reais.
- Não enfraqueça testes de arquitetura.

Arquitetura detalhada, pastas e nomenclatura: [`.agents/docs/arquitetura.md`](.agents/docs/arquitetura.md).  
Testes, jobs e checklist completo: [`.agents/docs/jobs-qualidade-checklist.md`](.agents/docs/jobs-qualidade-checklist.md).

---

## Regras de prioridade alta (índice)

1–13 estão cobertas nas **regras sempre on** acima. Domínio específico (detalhe nos docs):

| # | Tema | Doc |
|---|---|---|
| 14–15 | Projetos: planning, milestones, ciclos | [`dominio-tenant.md`](.agents/docs/dominio-tenant.md) |
| 16 | Comitê: modo reunião | [`dominio-tenant.md`](.agents/docs/dominio-tenant.md) |
| 17 | Deal room (oferta ≠ assinatura) | [`dominio-tenant.md`](.agents/docs/dominio-tenant.md) |
| 18 | Legalização: caminho crítico e custos | [`dominio-tenant.md`](.agents/docs/dominio-tenant.md) |
| 19 | Captura mobile (`client_id`, `base_version`, 409) | [`dominio-tenant.md`](.agents/docs/dominio-tenant.md) |
| 20 | Onboarding versionado / allowlist | [`dominio-tenant.md`](.agents/docs/dominio-tenant.md) |
| 21 | Report builder / schedules / catálogo fechado | [`dominio-tenant.md`](.agents/docs/dominio-tenant.md) |
| 22 | Inteligência documental (PDF, versões) | [`ia.md`](.agents/docs/ia.md) |

---

## Testes de arquitetura (não enfraquecer)

| Teste | Garante |
|---|---|
| `LayerBoundariesTest` | Controllers sem Eloquent direto; **Jobs com `failed()`** |
| `ServicesArchitectureTest` | Services sem Eloquent/`Request` indevidos |
| `AdminControllerArchitectureTest` | Admin sem validate/query inline |
| `PublicControllerArchitectureTest` | Público/auth sem validate inline |
| `ModulesControllerArchitectureTest` | ModulesController sem Models diretos |
| `TenantAdminRequestAuthorizationTest` | FormRequest tenant sem `return true;` |
| `TenantRoutesArchitectureTest` | Módulos tenant carregados uma vez |
| `RouteCacheArchitectureTest` | Nomes de rota únicos no cache |

---

## Checklist antes de cada PR

- [ ] `composer analyse` (PHPStan 8) sem erros novos
- [ ] `./vendor/bin/pint --test` limpo
- [ ] `composer test` verde, incluindo `--testsuite=Architecture`
- [ ] Rota: `/api/v1/...`, arquivo certo (central × tenant), throttle + middlewares de contexto/permissão
- [ ] Mutação com FormRequest + `authorize()` real; sem `$request->all()` / `->validate()` inline
- [ ] Resposta via `ApiResponseService`/Resource; i18n nos **dois** JSONs
- [ ] Sem N+1; sem `all()`/`get()` ilimitado
- [ ] Migration na pasta certa, com `down()`, índices; OK em SQLite nos testes
- [ ] Job novo com `failed()`, `$tries`, `$timeout`
- [ ] Model novo: `$fillable`, `$casts`, factory
- [ ] Repository com interface? Bind no `AppServiceProvider`
- [ ] `.env.example` se criou variável
- [ ] Doc em `.agents/docs/` (e hub/mapa se necessário) se mudou regra/fluxo/superfície
- [ ] Externos mockados nos testes

---

## Skills e docs de produto

- Skills de ferramenta (Stripe, deploy cloud, etc.): [`.agents/skills/`](.agents/skills/)
- Planos e análises datadas (não são contrato operacional): `docs/YYYY-MM-DD-*.md`

---

**Última atualização:** Agosto 2026 — monólito `AGENTS.md` reorganizado em hub + `.agents/docs/` (meio-termo). Conteúdo de domínio preservado nos arquivos filhos; hub concentra regras sempre on e mapa sob demanda.
