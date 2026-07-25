# Review técnico completo do backend — 2026-06-18

> **Atualizado em 2026-06-19:** Telescope foi removido do projeto (provider, config, migration e dependência), então a antiga seção `telescope/*` saiu da tabela. Cada seção de rota consumida pelo frontend recebeu um bloco **"Respostas"** com o modelo de payload de sucesso e os formatos de erro esperados — ver [Convenções de resposta da API](#convenções-de-resposta-da-api).

## Escopo e método

Review somente leitura de todo o código próprio inventariado em `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `resources/`, `tests/` e `scripts`: 953 arquivos, 923 PHP e 77.813 linhas PHP. Todas as 475 entradas de `route:list` foram inspecionadas e colapsadas por método/path/action/middleware em ~289 contratos únicos na tabela abaixo. Binários e artefatos gerados foram avaliados como artefatos; `vendor/` não recebeu review linha a linha, mas versões, advisories e os trechos relevantes de Sanctum/Cashier foram verificados.

Verificações executadas:

- `php artisan test`: **584 testes, 1.979 assertions, todos passando**.
- PHPStan nível 8: **sem erros** (revalidado em 2026-06-18 com PHP 8.4 e `--memory-limit=1G`).
- `composer audit --locked`: **sem advisories**.
- Inventário de secrets rastreados: nenhum token/chave privada hardcoded identificado pelos padrões usados.
- Migrations: todas declaram `down()`; as migrations de backfill irreversível agora falham explicitamente e instruem restore por backup.

## Contexto levantado

- **Stack:** PHP 8.4; Laravel 13.16.1; Eloquent; PostgreSQL; Sanctum 4.3.2; Cashier 16.5.3; `stripe/stripe-php` 17.6.0; Spatie Permission 7.4.1; `stancl/tenancy` 3.10.0; Laravel AI 0.7; PHPUnit 13; PHPStan 2.1.
- **Estrutura:** domínio central (`Models/Central`, admin, signup, billing, broker de login) e domínio tenant (`Models/Tenant`, controllers/services/repositories tenant), além de AI, jobs, events/listeners, policies e migrations separadas.
- **Tenancy:** um banco PostgreSQL compartilhado com **schema por tenant**, selecionado por conexão/search path dinâmico. Banco/conexão, cache, filesystem e queue recebem contexto do tenant. Dados centrais usam schema/conexão central. Não depende de `tenant_id` nas tabelas tenant.
- **Auth:** tokens pessoais Sanctum, com validade explícita de 7 dias para tenant e 12 horas para admin; guards de sessão `central_web` e `tenant_web` também estão habilitados. Login central usa diretório e ticket one-time de 90 segundos; logout de PAT remove token no servidor.
- **Autorização:** entitlements por plano (`broker`, `basico`, `master`, `pro`) em `CheckFeature`, limites em `EnforcePlanLimits`, RBAC Spatie em `PermissionGate`/`TenantPolicy`.
- **Stripe:** Checkout subscription, trials, subscriptions, swap, invoices, SetupIntent/payment methods, billing portal, coupons e webhooks. Eventos tratados: `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.created|updated|deleted|trial_will_end`, `charge.dispute.created|updated|closed` e `coupon.redeemed`.
- **⚠️ indefinido:** valores efetivos de `APP_DEBUG`, CORS, headers do proxy/CDN, queue workers e scheduler em produção. O código local mostra `queue=sync`; isso não foi projetado automaticamente para produção.

## Achados

### Severidade alta

### Severidade média

**[MÉDIO]** — `routes/tenant.php:226` → `GET api/v1/proprietarios/select`

> A rota registra `[ProprietariosController::class, 'proprietariosForSelect']`, mas esse método não existe no controller (nem em trait/base). Qualquer requisição ao endpoint lança `BadMethodCallException`, retornando **500** (`INTERNAL_ERROR` em produção). Os demais endpoints `*/select` do app usam o método `forSelect`.
> Impacto: o combo de proprietários no frontend não tem fonte de dados funcional.
> Correção sugerida: adicionar `proprietariosForSelect` ao controller (espelhando `RegionaisController::forSelect`) ou apontar a rota para o método correto.

### Severidade baixa

**[BAIXO]** — `app/Http/Controllers/Api/V1/Tenant/DashboardController.php` e serviços AI/dashboard

> Há validação inline, controller de 464 linhas e Eloquent direto em services, em desacordo com Controller→Service→Repository. Não identifiquei SQL injection nas expressões raw revisadas; os sorts controlados usam allowlist.
> Impacto: aumenta chance de inconsistência e dificulta testes, mas não é vulnerabilidade isolada.
> Correção sugerida: corrigir apenas ao tocar nesses fluxos por bugs; não recomendo refactor amplo neste PR.

## Stripe

- Assinatura: `VerifyWebhookSignature` é obrigatória fora de local/testing; ausência do secret retorna 503.
- Idempotência: lock por event ID + tabela central com ID único + `processed_at`; resposta não processada mantém evento reprocessável.
- Falha de pagamento: notifica toda tentativa e suspende a partir da terceira; `unpaid`/`incomplete_expired` também suspendem na reconciliação.
- Cancelamento: `customer.subscription.deleted` cancela tenant e Cashier sincroniza tabela local.
- Upgrade/downgrade: o servidor classifica a troca por `sort_order`; upgrades usam `swapAndInvoice`, downgrades ficam em `scheduled_plan_id` até `invoice.paid`.
- Trial: Stripe controla o período e o signup agora registra um ledger central por email para evitar repetir trial com novo slug no mesmo email. Ainda não há trava adicional por organização ou payment method.
- Cartão: dados completos não passam pelo backend; usa Checkout, SetupIntent e payment method IDs. Só brand/last4/expiração são lidos.
- `invoice.payment_action_required` agora possui handler próprio e notificação dedicada para ação adicional do cliente sem suspender a conta.

## Multi-tenancy e ownership

O isolamento primário por schema reduz a necessidade de `tenant_id` em cada query e foi aplicado ao grupo tenant antes de auth/controllers. Models centrais usam conexão central; cache/filesystem/queue são contextualizados. Não encontrei query HTTP tenant apontando explicitamente para outro schema nem endpoint administrativo cross-tenant sem `central.admin`.

Ownership dentro do mesmo tenant é majoritariamente **RBAC por módulo**, não ownership por criador/responsável. Isso parece intencional para operação colaborativa; se a regra desejada for “usuário só vê seus terrenos”, ela está **⚠️ indefinida** e não é aplicada globalmente. Há escopo por usuário em conversas AI, notificações e devices.

## Checklist do que está bem implementado

- Schema por tenant com contexto de DB/cache/filesystem/queue e separação central explícita.
- Webhook Stripe assinado, idempotente e com validação de session/customer/tenant.
- PATs têm expiração, login tem throttles específicos e tickets de transferência são one-time/curtos.
- Passwords usam cast `hashed`/bcrypt configurável (12 rounds no exemplo); sem armazenamento plaintext identificado.
- Quase todas as mutações usam FormRequest; policies e permission levels distinguem viewer/editor/manager.
- Rotas críticas de auth, signup, reset e aprovação têm rate limiting; nenhum endpoint crítico autenticado ficou sem throttle genérico.
- Upload de documentos: 3 MB, MIME/extensões allowlisted, nome aleatório e storage privado tenant-scoped.
- Resources são amplamente usados e os testes verificam campos Stripe sensíveis não expostos.
- Troca de plano do tenant não aceita mais decisão de prorrateio do cliente; upgrades e downgrades seguem regras server-side com cobertura de testes.
- Tools de IA para viabilidade, legalização, comitê e negociação agora exigem feature do plano e `Gate::viewAny` do model correto, com cobertura unitária de negação.
- Criação de regionais e terreno-produto agora passa por `Gate::allows('create', ...)`, com testes cobrindo `403` para perfil viewer.
- Chargebacks agora criam disputa local idempotente, colocam o tenant em `under_review` e tratam encerramento `won`/`lost` com testes dedicados.
- `invoice.payment_action_required` agora é tratado com notificação dedicada ao cliente e cobertura de idempotência/ausência de suspensão.
- PDFs gerados pela IA agora passam por sanitização com `HTMLPurifier`, bloqueio de esquemas URI e `disableJavascript()` antes do render.
- Logout e logout-all agora invalidam sessão stateful quando não há `PersonalAccessToken`; refresh rejeita tokens não refreshables sem chamar `delete()`.
- Dependências auditadas foram atualizadas (`laravel/framework` 13.16.1, `guzzlehttp/psr7` 2.12.1, `mtdowling/jmespath.php` 2.9.1) e `composer audit --locked` está limpo.
- Dashboard e export de checklist agora retornam mensagens 500 genéricas, sem expor `getMessage()` ao cliente.
- CORS em produção restringe subdomínios a `https`, e `SecurityHeaders` aplica `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` e HSTS em conexões seguras.
- Recálculo de AI scoring em massa agora exige `update` em `Terreno`, roda em job assíncrono e o GET individual não escreve mais por `?recalculate=true`.
- Signup agora registra `trial_ledger` central por email e impede novo trial para o mesmo email mesmo com outro slug/tenant.
- `AiMonitorService` agora usa as relações já carregadas de `tasks`, `legalizacao` e `etapas` no monitor de itens vencidos.
- `CalculateUsableAreaJob` agora compõe `uniqueId()` com `tenantId` e `terrenoId`, eliminando a colisão direta entre schemas no lock único.
- `TerrenoObserver` agora só dispara `CalculateUsableAreaJob` com tenant inicializado, e a suíte de `CalculateUsableAreaJobTest`/`TerrenoObserverTest` foi alinhada ao novo contrato do job e está passando.
- As migrations `2026_03_11_000002` e `2026_03_11_000003` agora tratam `down()` de forma explícita como irreversível, falhando com instrução operacional de restore por backup em vez de sugerir rollback automático inexistente.
- Operações compostas de signup, workflow, legalização, projeto, comitê, negociação e viabilidade usam transações.
- 584 testes passam, incluindo auth, billing/webhook, tenancy, ACL, recursos e fluxos de negócio; PHPStan nível 8 também passa sem erros.
- Nenhum uso de command execution/eval ou SQL raw concatenando input foi confirmado.
- Todas as migrations possuem método `down()` declarado; a maioria implementa rollback funcional.

## Resumo executivo — pendências principais

1. O app ainda não define CSP localmente; se o edge não aplicar política equivalente, a proteção contra carregamento de conteúdo ativo fica incompleta.
2. O controller de dashboard tenant segue fora do padrão Controller→Service→Repository, aumentando custo de manutenção e chance de regressão.
3. A regra de ownership global dentro do tenant continua indefinida fora dos módulos que aplicam escopo explícito por usuário.

## Convenções de resposta da API

Toda a API responde JSON. Salvo a paginação nativa (formato B abaixo), o corpo segue o envelope do `app/Services/ApiResponseService.php`. Os blocos **"Respostas"** de cada seção descrevem apenas o conteúdo de `data` (e o status) — os envelopes e erros transversais abaixo valem para todas as rotas e **não são repetidos** por endpoint.

> **Mensagens (`message`):** quando o valor é uma chave `UPPER_SNAKE_CASE` (ex.: `LOGIN_SUCCESS`), o backend traduz pelo locale ativo antes de enviar; strings já legíveis passam direto. O frontend deve tratar `message` como texto pronto para exibição, não como código estável — para lógica, use `error.code`.

### Sucesso

```jsonc
// 200 OK — recurso único / operação
{ "success": true, "data": { /* objeto do recurso */ }, "message": "OPERACAO_SUCESSO" }

// 201 Created
{ "success": true, "data": { /* recurso criado */ }, "message": "RECURSO_CRIADO" }

// 204 No Content — corpo vazio
```

### Paginação — formato A (`ApiResponseService::paginated`, `meta` plano)

```jsonc
{
  "success": true,
  "data": [ { /* item */ } ],
  "message": "DATA_RETRIEVED_SUCCESSFULLY",
  "meta": { "current_page": 1, "per_page": 10, "total": 42, "last_page": 5, "from": 1, "to": 10 }
}
```

### Paginação — formato B (`respondWithPagination`, coleção nativa do Laravel)

Sem `success`/`message`; traz `links` e um `meta` mais rico. É o formato dos `index` de CRUD (proprietários, corretores externos, etc.).

```jsonc
{
  "data": [ { /* item */ } ],
  "links": { "first": "...?page=1", "last": "...?page=5", "prev": null, "next": "...?page=2" },
  "meta": {
    "current_page": 1, "from": 1, "last_page": 5, "path": "https://.../api/v1/recurso",
    "per_page": 10, "to": 10, "total": 42,
    "links": [ { "url": null, "label": "&laquo; Previous", "active": false } ]
  }
}
```

> **⚠️ Inconsistência conhecida de paginação.** Nem todos os `index` usam o mesmo shape — trate cada um conforme documentado na sua seção:
> - **B + extras no topo** (produtos, regionais, terreno-produtos): formato B acima **mais** as chaves duplicadas `message`, `current_page`, `last_page`, `total`, `per_page` no nível raiz (via `->additional()`).
> - **`{ data, meta }` simples** (documentos): `data` (array) + `meta` com apenas `current_page`, `last_page`, `per_page`, `total` — sem `links`, sem `success`.
> - **Formato A** (`meta` plano com `success`/`message`): usado por alguns índices de domínio (viabilidades, etc.).
>
> Recomendação para o frontend: derivar paginação de `meta.current_page`/`meta.last_page`/`meta.total` quando existir `meta`; senão das chaves de topo.

### Erros transversais

```jsonc
// Envelope de erro padrão (400/401/403/404/409/429/500…)
{ "success": false, "error": { "code": "STRING_CODE", "message": "texto", "details": null } }

// 401 — token ausente/inválido/expirado (handler de AuthenticationException)
{ "success": false, "error": { "code": "UNAUTHENTICATED", "message": "Não autenticado." } }

// 403 — RBAC/policy negada (Gate::authorize)
{ "success": false, "error": { "code": "FORBIDDEN", "message": "Sem permissão" } }

// 404 — model binding ou rota inexistente
{ "success": false, "error": { "code": "NOT_FOUND", "message": "Rota ou recurso não encontrado" } }

// 422 — validação de FormRequest (dois campos espelhados: errors e error.details)
{
  "success": false,
  "message": "Os dados fornecidos são inválidos",
  "errors": { "campo": ["mensagem"] },
  "error": { "code": "VALIDATION_ERROR", "message": "Os dados fornecidos são inválidos", "details": { "campo": ["mensagem"] } }
}

// 429 — throttle de rota
{ "success": false, "error": { "code": "TOO_MANY_REQUESTS", "message": "Muitas requisições" } }
```

### Erros de plano/assinatura (rotas tenant)

Aplicados pelos middlewares antes do controller. Todos retornam **403** com o envelope de erro padrão e `error.details`:

| `error.code`            | Origem (middleware)        | `details` |
| ----------------------- | -------------------------- | --------- |
| `NO_PLAN`               | `check.feature` / `enforce.limits` | `null` |
| `PLAN_FEATURE_DISABLED` | `check.feature`            | `{ "feature": "ai", "plan": "basico" }` |
| `SUBSCRIPTION_INACTIVE` | `subscription.active`      | `{ "status": "suspended", "support_url": "...", "billing_portal_available": true }` |
| `TRIAL_ENDED`           | `subscription.active`      | `{ "trial_ended_at": "ISO8601", "support_url": "...", "billing_portal_available": true }` |
| `PLAN_LIMIT_EXCEEDED`   | `enforce.limits` (só POST) | `{ "resource": "terrenos", "plan": "Básico", "upgrade_url": "/api/v1/tenant/subscription/upgrade" }` |

Rotas com **"Plano mínimo"** na tabela passam por `check.feature`; rotas POST de criação de recurso limitado também por `enforce.limits`. Nos blocos por rota, "Erros de plano" referencia esta tabela.

## Tabela completa de endpoints

A tabela tem ~289 contratos únicos. Rotas centrais repetidas nos quatro domínios configurados foram colapsadas e exibem o domínio canônico; entradas tenant aparecem com `(tenant)`. “Plano mínimo” é derivado da matriz seed atual; `N/A` significa que a rota não usa feature gate de plano.

### raiz

| Método   | Path  | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ----- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `/` | Não  | N/A           | Não           | ok                    |

### api

| Método   | Path                    | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ----------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/health` (tenant) | Sim   | N/A           | Sim            | ok                    |

#### Respostas — api/health (tenant)

Health-check autenticado do tenant. **200** saudável, **503** quando `status === "down"` ou fora de contexto de tenant.

```jsonc
// 200 — relatório do HealthCheckService + bloco tenant
{ "status": "ok", "timestamp": "ISO8601", "checks": { /* ... */ },
  "tenant": { "id": "uuid", "name": "...", "status": "active" } }

// 503 — sem contexto de tenant
{ "status": "error", "timestamp": "ISO8601", "tenant": null }
```

### admin

| Método    | Path                                                                       | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | -------------------------------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/admin/acl/catalog` (sigapp.com.br)                               | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/acl/plans/{planId}/role-matrix` (sigapp.com.br)            | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/audit-logs` (sigapp.com.br)                                | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/coupons` (sigapp.com.br)                                   | Sim   | N/A           | Não           | ok                    |
| POST       | `api/v1/admin/coupons` (sigapp.com.br)                                   | Sim   | N/A           | Não           | ok                    |
| DELETE     | `api/v1/admin/coupons/{coupon}` (sigapp.com.br)                          | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/coupons/{coupon}` (sigapp.com.br)                          | Sim   | N/A           | Não           | ok                    |
| PUT\|PATCH | `api/v1/admin/coupons/{coupon}` (sigapp.com.br)                          | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/dashboard` (sigapp.com.br)                                 | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/entitlements` (sigapp.com.br)                              | Sim   | N/A           | Não           | ok                    |
| POST       | `api/v1/admin/entitlements` (sigapp.com.br)                              | Sim   | N/A           | Não           | ok                    |
| DELETE     | `api/v1/admin/entitlements/{entitlement}` (sigapp.com.br)                | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/entitlements/{entitlement}` (sigapp.com.br)                | Sim   | N/A           | Não           | ok                    |
| PUT\|PATCH | `api/v1/admin/entitlements/{entitlement}` (sigapp.com.br)                | Sim   | N/A           | Não           | ok                    |
| POST       | `api/v1/admin/login` (sigapp.com.br)                                     | Não  | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/plans` (sigapp.com.br)                                     | Sim   | N/A           | Não           | ok                    |
| POST       | `api/v1/admin/plans` (sigapp.com.br)                                     | Sim   | N/A           | Não           | ok                    |
| DELETE     | `api/v1/admin/plans/{plan}` (sigapp.com.br)                              | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/plans/{plan}` (sigapp.com.br)                              | Sim   | N/A           | Não           | ok                    |
| PUT\|PATCH | `api/v1/admin/plans/{plan}` (sigapp.com.br)                              | Sim   | N/A           | Não           | ok                    |
| PUT        | `api/v1/admin/plans/{plan}/entitlements` (sigapp.com.br)                 | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/posts` (sigapp.com.br)                                     | Sim   | N/A           | Não           | ok                    |
| POST       | `api/v1/admin/posts` (sigapp.com.br)                                     | Sim   | N/A           | Não           | ok                    |
| DELETE     | `api/v1/admin/posts/{post}` (sigapp.com.br)                              | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/posts/{post}` (sigapp.com.br)                              | Sim   | N/A           | Não           | ok                    |
| PUT\|PATCH | `api/v1/admin/posts/{post}` (sigapp.com.br)                              | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/tenants` (sigapp.com.br)                                   | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/tenants/{id}/entitlements` (sigapp.com.br)                 | Sim   | N/A           | Não           | ok                    |
| POST       | `api/v1/admin/tenants/{id}/entitlements` (sigapp.com.br)                 | Sim   | N/A           | Não           | ok                    |
| DELETE     | `api/v1/admin/tenants/{id}/entitlements/{entitlementId}` (sigapp.com.br) | Sim   | N/A           | Não           | ok                    |
| PUT        | `api/v1/admin/tenants/{id}/entitlements/{entitlementId}` (sigapp.com.br) | Sim   | N/A           | Não           | ok                    |
| POST       | `api/v1/admin/tenants/{id}/plan` (sigapp.com.br)                         | Sim   | N/A           | Não           | ok                    |
| PUT        | `api/v1/admin/tenants/{id}/plan/downgrade` (sigapp.com.br)               | Sim   | N/A           | Não           | ok                    |
| PUT        | `api/v1/admin/tenants/{id}/plan/upgrade` (sigapp.com.br)                 | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/tenants/{tenant}` (sigapp.com.br)                          | Sim   | N/A           | Não           | ok                    |
| POST       | `api/v1/admin/tenants/{tenant}/activate` (sigapp.com.br)                 | Sim   | N/A           | Não           | ok                    |
| POST       | `api/v1/admin/tenants/{tenant}/suspend` (sigapp.com.br)                  | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/users` (sigapp.com.br)                                     | Sim   | N/A           | Não           | ok                    |
| POST       | `api/v1/admin/users` (sigapp.com.br)                                     | Sim   | N/A           | Não           | ok                    |
| DELETE     | `api/v1/admin/users/{user}` (sigapp.com.br)                              | Sim   | N/A           | Não           | ok                    |
| GET\|HEAD  | `api/v1/admin/users/{user}` (sigapp.com.br)                              | Sim   | N/A           | Não           | ok                    |
| PUT\|PATCH | `api/v1/admin/users/{user}` (sigapp.com.br)                              | Sim   | N/A           | Não           | ok                    |

#### Respostas — admin (central, `central.admin`)

Objetos (timestamps ISO8601):

```jsonc
// Coupon
{ "id": 1, "stripe_coupon_id": "...", "code": "PROMO10", "name": "...", "description": "...",
  "type": "percent|amount", "amount_off": null, "percent_off": 10, "currency": "brl|null",
  "max_redemptions": 100, "times_redeemed": 5, "redeem_by": "ISO8601|null",
  "expires_after_first_redemption": false, "is_active": true,
  "applies_to_plans": ["basico"], "applies_to_tenants": [], "formatted_discount": "10%",
  "is_available": true, "created_at": "ISO8601", "updated_at": "ISO8601" }
// Entitlement
{ "id": 1, "key": "terrenos", "label": "...", "description": "...", "type": "limit|feature",
  "default_value": "...", "created_at": "ISO8601", "updated_at": "ISO8601" }
// TenantEntitlement (extra)
{ "id": 1, "entitlement_id": 2, "entitlement": { /* Entitlement quando carregado */ },
  "value": "...", "price": 9900, "price_formatted": "R$ 99,00", "created_at": "ISO8601", "updated_at": "ISO8601" }
// AdminPost
{ "id": 1, "title": "...", "slug": "...", "excerpt": "...", "content": "html", "category": "...",
  "image": "url|null", "read_time": "5 min", "featured": false, "published": true,
  "published_at": "ISO8601|null", "author": { "id": 1, "name": "..." }, "created_at": "ISO8601", "updated_at": "ISO8601" }
// AdminTenantSummary (index)
{ "id": "uuid", "name": "...", "slug": "...", "status": "active", "admin_name": "...", "admin_email": "...",
  "plan": { /* Plan quando carregado */ }, "trial_ends_at": "ISO8601|null", "on_trial": false, "trial_ended": false,
  "database_created": true, "setup_completed_at": "ISO8601|null", "created_at": "ISO8601", "updated_at": "ISO8601" }
// AdminTenantDetail (show) = Summary + stripe_id, stripe_subscription_id, trial_extended, "stats": {...}, "finance": {...}, "plan"
// DashboardStats
{ "total_tenants": 0, "active_tenants": 0, "pending_tenants": 0, "suspended_tenants": 0,
  "cancelled_tenants": 0, "today_tenants": 0, "trial_tenants": 0, "trial_expired_tenants": 0, "mrr": 0 }
```

- **POST `admin/login`** — 200 `{ "success": true, "data": { "user": { /* CentralUserResource — ver auth/me */ }, "token": "...", /* expires_at */ }, "message": "..." }`. **401**/**4xx** em credenciais inválidas.
- **GET `admin/dashboard`** — 200 `{ "success": true, "data": { /* DashboardStats + séries */ }, "message"? }`.
- **GET `admin/acl/catalog`** — 200 `{ "success": true, "data": { /* módulos, roles, permissões */ } }`. **GET `admin/acl/plans/{planId}/role-matrix`** — 200 `{ "success": true, "data": { /* matriz role×permissão */ } }`; **404** `Plano não encontrado`.
- **GET `admin/audit-logs`** — 200 `{ "success": true, "data": { /* logs (paginados) */ }, "message": "Logs de auditoria recuperados" }`.
- **coupons**: `index` → paginação **formato A** (Coupon); `show/store/update` → `{ "success": true, "data": { /* Coupon */ } }` (200/201); `destroy` → 200 `{ "success": true, "data": null, "message": "..." }` (desativa, não apaga).
- **entitlements**: `index` → `{ "success": true, "data": [ /* Entitlement */ ] }` (sem paginação); `show/store/update` → `{ "success": true, "data": { /* Entitlement */ } }`; `store` **409** em duplicado; `destroy` → `{ "success": true, "data": null, "message": "Entitlement removido com sucesso" }`.
- **plans**: `index` → `{ "success": true, "data": [ /* Plan */ ] }`; `show/store/update` → Plan (200/201); `destroy` → 200 ou **409** `CONFLICT`; `PUT plans/{plan}/entitlements` → 200 com plano sincronizado.
- **posts**: `index` → paginação **formato A** (AdminPost); `show/store/update` → AdminPost; `destroy` → 200 `{ "success": true, "data": null, "message": "Artigo excluído com sucesso" }`.
- **tenants**: `index` → paginação **formato A** (AdminTenantSummary); `show` → `{ "success": true, "data": { /* AdminTenantDetail */ } }`; `activate`/`suspend` → 200 com tenant atualizado, ou **4xx** `ALREADY_ACTIVE`/`ALREADY_SUSPENDED`/`BILLING_STATE_INVALID`; `GET/POST/PUT/DELETE {id}/entitlements` → TenantEntitlement (lista/criação/atualização, **409** duplicado, **422** inválido); `POST {id}/plan`, `PUT {id}/plan/upgrade`, `PUT {id}/plan/downgrade` → 200 com plano, ou **422** `INVALID_PLAN`/`UPGRADE_FAILED`/`DOWNGRADE_FAILED`.
- **users**: `index` → paginação **formato A**; `show/store/update` → CentralUserResource; `destroy` → 200 `{ "success": true, "data": null, "message": "Usuário excluído com sucesso" }`, ou **4xx** `SELF_DELETION`.
- **422** validação; **403** `central.admin`; **401** sem token.

### ai

| Método   | Path                                                    | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados                                                |
| --------- | ------------------------------------------------------- | ----- | ------------- | -------------- | -------------------------------------------------------------------- |
| GET\|HEAD | `api/v1/ai/automation/monitor` (tenant)               | Sim   | Master        | Sim            | ok                                                                   |
| POST      | `api/v1/ai/automation/tasks` (tenant)                 | Sim   | Master        | Sim            | ok                                                                   |
| PUT       | `api/v1/ai/automation/tasks/{taskId}` (tenant)        | Sim   | Master        | Sim            | ok                                                                   |
| POST      | `api/v1/ai/automation/workflow/transition` (tenant)   | Sim   | Master        | Sim            | ok                                                                   |
| GET\|HEAD | `api/v1/ai/budget` (tenant)                           | Sim   | Master        | Sim            | ok                                                                   |
| GET\|HEAD | `api/v1/ai/conversations` (tenant)                    | Sim   | Master        | Sim            | ok                                                                   |
| GET\|HEAD | `api/v1/ai/conversations/{id}/messages` (tenant)      | Sim   | Master        | Sim            | ok                                                                   |
| GET\|HEAD | `api/v1/ai/predictive/approval/{terreno_id}` (tenant) | Sim   | Master        | Sim            | ok                                                                   |
| GET\|HEAD | `api/v1/ai/predictive/stalling` (tenant)              | Sim   | Master        | Sim            | ok                                                                   |
| GET\|HEAD | `api/v1/ai/predictive/vgv/{terreno_id}` (tenant)      | Sim   | Master        | Sim            | ok                                                                   |
| GET\|HEAD | `api/v1/ai/scoring/{terreno_id}` (tenant)             | Sim   | Master        | Sim            | ok                                                                   |
| GET\|HEAD | `api/v1/ai/scoring/ranking` (tenant)                  | Sim   | Master        | Sim            | ok                                                                   |
| POST      | `api/v1/ai/scoring/recalculate` (tenant)              | Sim   | Master        | Sim            | ok                                                                   |
| POST      | `api/v1/ai/sig-ai` (tenant)                           | Sim   | Master        | Sim            | ALTO: tools consultam módulos Pro usando só permissão de terrenos |

#### Respostas — ai (plano Master)

> ⚠️ A seção AI usa envelope **`{ "data": ... }` sem `success`** e erros próprios **`{ "message": "..." }`** (403/404/400) — diferente do restante da API. Além disso, os middlewares de plano (`PLAN_FEATURE_DISABLED`), orçamento (`ai.budget`) e rate-limit do provedor (`AI_PROVIDER_RATE_LIMITED`, 429) seguem o envelope de erro padrão (`{ "success": false, "error": {...} }`).

- **GET `ai/automation/monitor`** — 200 `{ "data": { /* itens vencidos/alertas */ } }`. **403** `{ "message": "Acesso negado." }`.
- **POST `ai/automation/tasks`** — 200 `{ "data": { /* tarefa criada */ } }`. **403** `{ "message": "Acesso negado ao terreno." }`.
- **PUT `ai/automation/tasks/{taskId}`** — 200 `{ "data": { /* tarefa */ } }`. **404** `{ "message": "Tarefa não encontrada." }`; **400** `{ "message": "Nenhuma alteração informada." }`; **403**.
- **POST `ai/automation/workflow/transition`** — 200 `{ "data": { /* novo estado */ } }`. **403**.
- **GET `ai/budget`** — 200 `{ "data": { /* status de orçamento de IA */ } }`.
- **GET `ai/conversations`** — 200 `{ "data": [ /* conversas */ ] }`.
- **GET `ai/conversations/{id}/messages`** — 200 `{ "data": [ /* mensagens */ ] }`. **404** `{ "message": "Conversa não encontrada." }`.
- **GET `ai/predictive/approval/{terreno_id}`** · **`ai/predictive/vgv/{terreno_id}`** — 200 `{ "data": { /* previsão */ } }`. **404** `{ "message": "Terreno não encontrado." }`; **403** `{ "message": "Acesso negado ao terreno." }`.
- **GET `ai/predictive/stalling`** — 200 `{ "data": { /* previsão de estagnação */ } }`.
- **GET `ai/scoring/{terreno_id}`** — 200 `{ "data": { "terreno_id": 10, "terreno_nome": "...", /* ...score */ } }`. **404**/**403** `{ "message": "..." }`.
- **GET `ai/scoring/ranking`** — 200 `{ "data": [ /* ranking */ ] }`.
- **POST `ai/scoring/recalculate`** — **202** `{ "message": "Recálculo de scores enfileirado." }` (job assíncrono).
- **POST `ai/sig-ai`** — chat do assistente: 200 `{ "data": { /* resposta do assistente + conversation_id */ } }` (ou stream). **429** `{ "success": false, "error": { "code": "AI_PROVIDER_RATE_LIMITED" } }`. ⚠️ ALTO: tools consultam módulos Pro usando só permissão de terrenos (ver tabela/Achados).
- **403** plano (`PLAN_FEATURE_DISABLED`, Master) e RBAC; **401** sem token.

### auth

| Método   | Path                                            | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados                                      |
| --------- | ----------------------------------------------- | ----- | ------------- | -------------- | ---------------------------------------------------------- |
| POST      | `api/v1/auth/exchange-ticket` (tenant)        | Não  | N/A           | Sim            | ok                                                         |
| POST      | `api/v1/auth/login` (sigapp.com.br)           | Não  | N/A           | Não           | ok                                                         |
| POST      | `api/v1/auth/login` (tenant)                  | Não  | N/A           | Sim            | ok                                                         |
| POST      | `api/v1/auth/logout` (sigapp.com.br)          | Sim   | N/A           | Não           | ok                                                         |
| POST      | `api/v1/auth/logout` (tenant)                 | Sim   | N/A           | Sim            | ok                                                         |
| POST      | `api/v1/auth/logout-all` (sigapp.com.br)      | Sim   | N/A           | Não           | ok                                                         |
| POST      | `api/v1/auth/logout-all` (tenant)             | Sim   | N/A           | Sim            | ok                                                         |
| GET\|HEAD | `api/v1/auth/me` (sigapp.com.br)              | Sim   | N/A           | Não           | ok                                                         |
| GET\|HEAD | `api/v1/auth/me` (tenant)                     | Sim   | N/A           | Sim            | ok                                                         |
| PUT       | `api/v1/auth/me` (tenant)                     | Sim   | N/A           | Sim            | ok                                                         |
| POST      | `api/v1/auth/password/forgot` (sigapp.com.br) | Não  | N/A           | Não           | ok                                                         |
| POST      | `api/v1/auth/password/forgot` (tenant)        | Não  | N/A           | Sim            | ok                                                         |
| POST      | `api/v1/auth/password/reset` (sigapp.com.br)  | Não  | N/A           | Não           | ok                                                         |
| POST      | `api/v1/auth/password/reset` (tenant)         | Não  | N/A           | Sim            | ok                                                         |
| POST      | `api/v1/auth/refresh` (sigapp.com.br)         | Sim   | N/A           | Não           | ok                                                         |
| POST      | `api/v1/auth/refresh` (tenant)                | Sim   | N/A           | Sim            | ok                                                         |
| POST      | `api/v1/auth/select-tenant` (sigapp.com.br)   | Não  | N/A           | Não           | ok                                                         |

#### Respostas — auth

**Payload de token** (login tenant, `exchange-ticket`): `data` traz `user` (objeto do usuário — ver `auth/me` abaixo), `token` (Bearer em texto puro), `abilities` e `expires_at` (ISO8601).

```jsonc
// POST auth/login (tenant) · POST auth/exchange-ticket — 200
{
  "success": true,
  "data": {
    "user": { /* UserResource — ver auth/me */ },
    "token": "12|abcdef...",
    "abilities": ["tenant-api"],
    "expires_at": "2026-06-26T12:00:00+00:00"
  },
  "message": "Login realizado com sucesso"
}
```

- `auth/login` (tenant): **401** `{ "code": "UNAUTHORIZED", "message": "Credenciais inválidas" }`; **422** validação de `email`, `password`, `device_name`.
- `auth/exchange-ticket`: **401** `{ "code": "INVALID_TRANSFER_TICKET" }` (ticket inválido/usado/expirado); **422** validação.

**POST `auth/login` (sigapp.com.br — broker central):** `data` varia conforme `next_action`.

```jsonc
// (a) host já é tenant → idêntico ao payload de token acima (message "Login realizado com sucesso")

// (b) múltiplos tenants para o e-mail — message "CHOOSE_TENANT"
{ "success": true, "message": "...", "data": {
  "next_action": "choose_tenant",
  "broker_session_id": "uuid",
  "expires_at": "2026-06-19T12:01:30+00:00",
  "tenants": [
    { "id": "uuid", "name": "Incorporadora X", "slug": "x", "tenant_url": "https://x.sigapp.com.br" }
  ]
}}

// (c) tenant único — message "REDIRECT_READY"
{ "success": true, "message": "...", "data": {
  "next_action": "redirect",
  "tenant": { "id": "uuid", "name": "Incorporadora X", "slug": "x" },
  "tenant_url": "https://x.sigapp.com.br",
  "transfer_ticket": "texto-puro",
  "transfer_ticket_expires_at": "2026-06-19T12:01:30+00:00"
}}
```

- **401** `{ "code": "UNAUTHORIZED", "message": "Credenciais inválidas" }`; **404** `{ "code": "NOT_FOUND" }` (tenant do host não existe); **422** validação; **429** throttle.

**POST `auth/select-tenant`:** 200 com `data` no formato `redirect` (c acima), `message` `REDIRECT_READY`. **410** `{ "code": "BROKER_SESSION_INVALID" }` (sessão do broker expirada/usada); **422** validação de `broker_session_id`/`tenant_id`.

**POST `auth/logout` · `auth/logout-all`:** 200 `{ "success": true, "data": null, "message": "..." }` (`LOGOUT_SUCCESS` / `LOGOUT_ALL_DEVICES_SUCCESS`). **401** sem token.

**POST `auth/refresh`:** 200 `{ "success": true, "data": { "token": "novo", "expires_at": "ISO8601" }, "message": "TOKEN_RENEWED" }`. **401** `{ "code": "UNAUTHORIZED", "message": "INVALID_TOKEN" }` sem token; **422** `{ "code": "INVALID_TOKEN", "message": "TOKEN_NOT_REFRESHABLE" }` para sessão stateful (sem PAT).

**GET `auth/me`:** 200, `message` `USER_RETRIEVED`. `data` = objeto do usuário no contexto atual.

```jsonc
// Tenant (UserResource) — campos nullable quando não preenchidos
{
  "id": 1, "name": "...", "email": "...", "email_verified_at": "ISO8601|null",
  "role": "Gerente|null", "roles": ["Gerente"],
  "permissions": ["terrenos.viewer", "viabilidades.editor"],
  "department": { /* DepartmentResource, só quando carregado */ }, "department_id": 1,
  "position": { /* PositionResource, só quando carregado */ }, "position_id": 1,
  "phone": null, "cpf": null, "rg": null, "birth_date": null, "gender": null,
  "address": null, "city": null, "state": null, "country": null, "zip_code": null,
  "profile_picture": null, "status": "active", "locale": "pt-br",
  "created_at": "ISO8601", "updated_at": "ISO8601"
}

// Central admin (CentralUserResource)
{
  "id": 1, "name": "...", "email": "...", "is_admin": true, "email_verified_at": "ISO8601|null",
  "role": "sigapp|null", "roles": ["sigapp"], "permissions": ["modulo.viewer", "..."],
  "created_at": "ISO8601", "updated_at": "ISO8601"
}
```

**PUT `auth/me`:** 200, `data` = UserResource atualizado, `message` `USER_UPDATED_SUCCESSFULLY`. **422** validação. **401** sem token.

**POST `auth/password/forgot`:** sempre 200 (não revela existência do e-mail) `{ "success": true, "data": { "status": "passwords.sent" }, "message": "PASSWORD_RESET_LINK_SENT" }`. **422** validação de `email`; **429** throttle.

**POST `auth/password/reset`:** 200 `{ "success": true, "data": { "status": "passwords.reset" }, "message": "PASSWORD_RESET_SUCCESS" }`. Erros **422**: `{ "code": "INVALID_RESET_TOKEN" }`, `{ "code": "INVALID_RESET_USER" }`, `{ "code": "PASSWORD_RESET_FAILED" }` ou validação de campos; **404** `{ "code": "NOT_FOUND" }` (reset central sem tenant resolvido); **429** throttle.

### blog

| Método   | Path                                       | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ------------------------------------------ | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/blog` (sigapp.com.br)            | Não  | N/A           | Não           | ok                    |
| GET\|HEAD | `api/v1/blog/{slug}` (sigapp.com.br)     | Não  | N/A           | Não           | ok                    |
| GET\|HEAD | `api/v1/blog/categories` (sigapp.com.br) | Não  | N/A           | Não           | ok                    |

#### Respostas — blog (público)

```jsonc
// objeto BlogPostSummary
{
  "id": 1, "title": "...", "slug": "...", "excerpt": "...", "category": "...",
  "image": "url|null", "read_time": "5 min|null", "featured": false,
  "published_at": "ISO8601|null",
  "author": { "id": 1, "name": "..." }   // só quando carregado
}
```

- **GET `blog`** — 200, paginação **formato B** (coleção nativa) + `"success": true` no topo. Itens = `BlogPostSummary`.
- **GET `blog/{slug}`** — 200 `{ "success": true, "data": { "post": { /* BlogPostSummary + "content": "html" */ }, "related": [ /* BlogPostSummary */ ] } }`. **404** se slug não publicado.
- **GET `blog/categories`** — 200 `{ "success": true, "data": ["Mercado", "Jurídico", ...] }` (strings únicas).

### cidades

| Método   | Path                                 | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ------------------------------------ | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/cidades/{estado}` (tenant) | Sim   | Broker        | Sim            | ok                    |
| GET\|HEAD | `api/v1/cidades/buscar` (tenant)   | Sim   | Broker        | Sim            | ok                    |
| GET\|HEAD | `api/v1/cidades/dados` (tenant)    | Sim   | Broker        | Sim            | ok                    |
| GET\|HEAD | `api/v1/cidades/estados` (tenant)  | Sim   | Broker        | Sim            | ok                    |

#### Respostas — cidades

> ⚠️ `buscar` e `dados` usam envelope `{ "status": "OK" | "ERROR" }` (não `success`). `estados` e `{estado}` usam `{ "success": true }`.

- **GET `cidades/estados`** — 200 `{ "success": true, "data": [ { "state_code": "SP", "state": "São Paulo" } ] }`.
- **GET `cidades/{estado}`** (cidades do estado) — 200 `{ "success": true, "data": [ { "code": "3550308", "name": "São Paulo" } ] }`.
- **GET `cidades/buscar?termo=`** — 200 `{ "status": "OK", "data": [ { "code": "3550308", "city": "São Paulo", "state": "São Paulo", "state_code": "SP" } ] }`.
- **GET `cidades/dados?cityCode=`** — 200 `{ "status": "OK", "data": { "id": 1, "code": "3550308", "city": "...", "state": "...", "state_code": "SP", "latitude": 0, "longitude": 0, "capital": false, "area_code": "11", "timezone": "...", "population": 0, "employed": 0, "per_capta_income": 0, "property_maximum_value": 0, "buyer_demand": 0, "own_property": 0, "rented_property": 0 } }`. **404** `{ "status": "ERROR", "message": "Cidade não encontrada" }`.
- **422** validação (`termo`/`cityCode`); **401** sem token.

### comite

| Método   | Path                                               | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | -------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/comite` (tenant)                         | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/comite` (tenant)                         | Sim   | Pro           | Sim            | ok                    |
| GET\|HEAD | `api/v1/comite/{id}` (tenant)                    | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/comite/{id}/decision` (tenant)           | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/comite/{id}/department-reviews` (tenant) | Sim   | Pro           | Sim            | ok                    |

#### Respostas — comite (plano Pro)

```jsonc
// objeto ComiteRevisao
{
  "id": 1, "terreno_id": 10, "viabilidade_id": 5, "status": "em_analise",
  "final_decision": "aprovado|reprovado|null", "final_comments": "string|null",
  "required_departments": ["juridico", "engenharia"],
  "decided_by": 3, "decided_at": "ISO8601|null",
  "terreno": { /* TerrenoResource — só quando carregado */ },
  "viabilidade": { /* ViabilidadeResource — só quando carregado */ },
  "pareceres_departamento": [ /* ComiteParecerDepartamento, quando carregado */ ],
  "pendencias": [
    { "id": 1, "title": "...", "description": "...", "severity": "alta", "status": "aberta",
      "department_code": "juridico", "responsible_user_id": 2, "due_date": "YYYY-MM-DD", "...": "..." }
  ]
}
```

- **GET `comite`** — 200, paginação **formato A** (`meta` plano).
- **POST `comite`** — 201 `{ "success": true, "data": { /* ComiteRevisao */ }, "message": "Comitê criado com sucesso" }`.
- **GET `comite/{id}`** — 200 `{ "success": true, "data": { /* ComiteRevisao */ } }`.
- **POST `comite/{id}/department-reviews`** — 200 `{ "success": true, "data": { /* ComiteRevisao atualizado */ }, "message": "Parecer registrado com sucesso" }`.
- **POST `comite/{id}/decision`** — 200 `{ "success": true, "data": { /* ComiteRevisao */ }, "message": "Decisão de comitê registrada com sucesso" }`.
- **404** model binding; **422** validação; **403** plano (Pro)/RBAC; **401** sem token.

### consent-log

| Método | Path                                   | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ------- | -------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| POST    | `api/v1/consent-log` (sigapp.com.br) | Não  | N/A           | Não           | ok                    |

#### Respostas — consent-log (público)

- **POST `consent-log`** — **201** `{ "success": true, "data": { "consent_id": "..." }, "message": "..." }` no primeiro registro do `consent_id`; **200** com o mesmo corpo nos registros seguintes. Cada chamada válida cria uma nova linha append-only para preservar o histórico. **422** validação.

### contratos

| Método   | Path                                    | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | --------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/contratos` (tenant)           | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/contratos` (tenant)           | Sim   | Pro           | Sim            | ok                    |
| GET\|HEAD | `api/v1/contratos/{id}` (tenant)      | Sim   | Pro           | Sim            | ok                    |
| PUT       | `api/v1/contratos/{id}` (tenant)      | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/contratos/{id}/sign` (tenant) | Sim   | Pro           | Sim            | ok                    |

#### Respostas — contratos (plano Pro)

```jsonc
// objeto Contrato
{
  "id": 1, "terreno_id": 10, "negociacao_id": 4, "contract_type": "compra_venda",
  "contract_number": "...", "signed_at": "ISO8601|null",
  "start_date": "YYYY-MM-DD|null", "end_date": "YYYY-MM-DD|null",
  "status": "rascunho|assinado|...", "file_path": "string|null", "notes": "string|null",
  "partes": [
    { "id": 1, "name": "...", "document": "...", "party_type": "comprador|vendedor",
      "signer_name": "...", "signer_document": "..." }
  ]
}
```

- **GET `contratos`** — 200, paginação **formato A**.
- **POST `contratos`** — 201 `{ "success": true, "data": { /* Contrato */ }, "message": "Contrato criado com sucesso" }`.
- **GET `contratos/{id}`** — 200 `{ "success": true, "data": { /* Contrato */ } }`.
- **PUT `contratos/{id}`** — 200 `{ "success": true, "data": { /* Contrato */ }, "message": "Contrato atualizado com sucesso" }`.
- **POST `contratos/{id}/sign`** — 200 `{ "success": true, "data": { /* Contrato com signed_at */ }, "message": "Contrato assinado com sucesso" }`.
- **404** model binding; **422** validação; **403** plano (Pro)/RBAC; **401** sem token.

### corretores-externos

| Método    | Path                                                         | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | ------------------------------------------------------------ | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/corretores-externos` (tenant)                      | Sim   | N/A           | Sim            | ok                    |
| POST       | `api/v1/corretores-externos` (tenant)                      | Sim   | N/A           | Sim            | ok                    |
| DELETE     | `api/v1/corretores-externos/{corretores_externo}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/corretores-externos/{corretores_externo}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/corretores-externos/{corretores_externo}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/corretores-externos/select` (tenant)               | Sim   | N/A           | Sim            | ok                    |

#### Respostas — corretores-externos

Recurso `CorretorExterno`. Timestamps em `Y-m-d H:i:s`.

```jsonc
// objeto CorretorExterno
{
  "id": 1, "nome": "...", "email": "...|null", "telefone": "...|null", "creci": "...|null",
  "telefone_formatado": "(11) 99999-9999|null", "creci_formatado": "...|null",
  "created_at": "2026-06-19 10:00:00", "updated_at": "2026-06-19 10:00:00"
}
```

- **GET `corretores-externos`** — 200, paginação **formato B puro** (`data`+`links`+`meta`, sem `success`).
- **GET `corretores-externos/select`** — 200 `{ "data": [ /* projeção enxuta */ ] }` (envelope cru, sem `success`/`message`).
- **POST** — 201 `{ "success": true, "data": { /* objeto */ }, "message": "..." }`.
- **GET/PUT `.../{corretores_externo}`** — 200 `{ "success": true, "data": { /* objeto */ }, "message": "..." }`.
- **DELETE** — **204 No Content** (corpo vazio).
- **404** `{ "code": "NOT_FOUND" }`; **422** validação; **401** sem token.

### dashboard

| Método   | Path                                                        | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados                             |
| --------- | ----------------------------------------------------------- | ----- | ------------- | -------------- | ------------------------------------------------- |
| GET\|HEAD | `api/v1/dashboard/anos-disponiveis` (tenant)              | Sim   | Básico       | Sim            | ok                                                |
| GET\|HEAD | `api/v1/dashboard/area-opcao-detalhe` (tenant)            | Sim   | Básico       | Sim            | ok                                                |
| GET\|HEAD | `api/v1/dashboard/cadastros-mensais` (tenant)             | Sim   | Básico       | Sim            | ok                                                |
| GET\|HEAD | `api/v1/dashboard/cadastros-mensais-responsavel` (tenant) | Sim   | Básico       | Sim            | ok                                                |
| GET\|HEAD | `api/v1/dashboard/cards` (tenant)                         | Sim   | Básico       | Sim            | ok                                                |
| GET\|HEAD | `api/v1/dashboard/overview` (tenant)                      | Sim   | Básico       | Sim            | ok                                                |
| GET\|HEAD | `api/v1/dashboard/resumo` (tenant)                        | Sim   | Básico       | Sim            | ok                                                |
| GET\|HEAD | `api/v1/dashboard/status-chart` (tenant)                  | Sim   | Básico       | Sim            | ok                                                |
| GET\|HEAD | `api/v1/dashboard/terrenos-responsavel` (tenant)          | Sim   | Básico       | Sim            | ok                                                |
| GET\|HEAD | `api/v1/dashboard/top-cidades` (tenant)                   | Sim   | Básico       | Sim            | ok                                                |
| GET\|HEAD | `api/v1/dashboard/unidades-fechadas-anual` (tenant)       | Sim   | Master        | Sim            | ok                                                |
| GET\|HEAD | `api/v1/dashboard/vgv-anual` (tenant)                     | Sim   | Master        | Sim            | ok                                                |

#### Respostas — dashboard

Todos os endpoints retornam `{ "success": true, "data": <payload>, /* algumas rotas incluem chaves extras como ano/filtro */ }`. Os payloads vêm do `DashboardQueryService` (KPIs e séries para gráficos). Erros de filtro retornam **422** `{ "success": false, "message": "..." }` (envelope simplificado, sem `error.code`).

```jsonc
// GET dashboard/cards — KPIs agregados
{ "success": true, "data": { /* ex.: total_terrenos, vgv_total, em_negociacao, ... */ } }

// GET dashboard/status-chart — série por status
{ "success": true, /* filtro/ano */ "data": [ { "status": "...", "total": 0 } ] }
```

- **GET `dashboard/cards`** · **`overview`** · **`resumo`** · **`anos-disponiveis`** · **`area-opcao-detalhe`** · **`status-chart`** · **`cadastros-mensais`** · **`cadastros-mensais-responsavel`** · **`terrenos-responsavel`** · **`top-cidades`** — 200 `{ "success": true, "data": ... }` (plano Básico).
- **GET `dashboard/vgv-anual`** · **`unidades-fechadas-anual`** — 200 `{ "success": true, "data": ... }` (plano Master).
- **422** `{ "success": false, "message": "Ano é obrigatório para filtros \"ano\" ou \"mes\"" }` quando faltam parâmetros de período em rotas que exigem.
- **403** plano/RBAC; **401** sem token. As mensagens 500 são genéricas (sem `getMessage()` ao cliente).

### documentos

| Método    | Path                                         | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | -------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/documentos` (tenant)               | Sim   | N/A           | Sim            | ok                    |
| POST       | `api/v1/documentos` (tenant)               | Sim   | N/A           | Sim            | ok                    |
| DELETE     | `api/v1/documentos/{documento}` (tenant)   | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/documentos/{documento}` (tenant)   | Sim   | N/A           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/documentos/{documento}` (tenant)   | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/documentos/{id}/download` (tenant) | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/documentos/{id}/view` (tenant)     | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/documentos/categorias` (tenant)    | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/documentos/tipos` (tenant)         | Sim   | N/A           | Sim            | ok                    |

#### Respostas — documentos

> ⚠️ Esta seção usa envelope **sem `success`** nas mutações: apenas `{ "message", "data" }`. Trate `data` diretamente.

Recurso `Documento` (timestamps `Y-m-d H:i:s`):

```jsonc
// objeto Documento
{
  "id": 1, "terreno_id": 10, "nome": "Matrícula.pdf",
  "tipo": "matricula", "tipo_label": "Matrícula", "categoria": "juridico", "categoria_label": "Jurídico",
  "descricao": "string|null",
  "view_url": "https://.../api/v1/documentos/1/view",
  "download_url": "https://.../api/v1/documentos/1/download",
  "tamanho": 123456, "tamanho_formatado": "120.56 KB",
  "status": "ativo", "status_label": "Ativo",
  "terreno": { "id": 10, "nome": "..." },          // só quando carregado
  "created_by": { "id": 1, "name": "..." },        // só quando carregado
  "updated_by": { "id": 1, "name": "..." },        // só quando carregado
  "created_at": "2026-06-19 10:00:00", "updated_at": "2026-06-19 10:00:00"
}
```

- **GET `documentos`** — 200 `{ "data": [ /* objetos */ ], "meta": { "current_page", "last_page", "per_page", "total" } }` (sem `links`, sem `success`).
- **POST** — 201 `{ "message": "Documento enviado com sucesso.", "data": { /* objeto */ } }`. **422** `{ "message": "Arquivo inválido." }` (sem envelope de erro) ou validação padrão.
- **GET `documentos/{documento}`** — 200 `{ "data": { /* objeto */ } }`.
- **PUT** — 200 `{ "message": "Documento atualizado com sucesso.", "data": { /* objeto */ } }`.
- **DELETE** — 200 `{ "message": "Documento excluído com sucesso." }`.
- **GET `documentos/{id}/download`** — **stream binário** (`Content-Disposition: attachment`). **GET `.../{id}/view`** — **stream binário inline**. Ambos retornam **404** `{ "message": "Arquivo não encontrado." }` se o arquivo físico sumir.
- **GET `documentos/tipos`** / **`documentos/categorias`** — 200 `{ "data": [ { "value": "matricula", "label": "Matrícula" }, ... ] }` (enumerações fixas).
- **403** `{ "code": "FORBIDDEN" }` (policy); **404** model binding; **401** sem token.

### health

| Método   | Path                                      | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados                                        |
| --------- | ----------------------------------------- | ----- | ------------- | -------------- | ------------------------------------------------------------ |
| GET\|HEAD | `api/v1/health`                         | Não  | N/A           | Não           | BAIXO: health público executa checks externos; há throttle |
| GET\|HEAD | `api/v1/health/details` (sigapp.com.br) | Sim   | N/A           | Não           | ok                                                           |

#### Respostas — health

Relatório do `HealthCheckService::check()`. Status HTTP segue a saúde: **200** ok/degradado, **503** quando `status === "down"`.

```jsonc
// GET health (público, payload mínimo) e health/details (autenticado, detalhado)
{ "status": "ok", "timestamp": "ISO8601", "checks": { /* database, cache, etc. */ } }
```

- **GET `health`** — público; **GET `health/details`** — requer auth central. As chaves de `checks` seguem o que `HealthCheckService` reporta.

### legalizacoes

| Método    | Path                                                                | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | ------------------------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/legalizacoes` (tenant)                                    | Sim   | Pro           | Sim            | ok                    |
| POST       | `api/v1/legalizacoes` (tenant)                                    | Sim   | Pro           | Sim            | ok                    |
| POST       | `api/v1/legalizacoes/{id}/recalcular-progresso` (tenant)          | Sim   | Pro           | Sim            | ok                    |
| POST       | `api/v1/legalizacoes/{id}/sync-gantt` (tenant)                    | Sim   | Pro           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/legalizacoes/{legalizacaoId}/etapas` (tenant)             | Sim   | Pro           | Sim            | ok                    |
| POST       | `api/v1/legalizacoes/{legalizacaoId}/etapas` (tenant)             | Sim   | Pro           | Sim            | ok                    |
| DELETE     | `api/v1/legalizacoes/{legalizacaoId}/etapas/{id}` (tenant)        | Sim   | Pro           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/legalizacoes/{legalizacaoId}/etapas/{id}` (tenant)        | Sim   | Pro           | Sim            | ok                    |
| PUT        | `api/v1/legalizacoes/{legalizacaoId}/etapas/{id}` (tenant)        | Sim   | Pro           | Sim            | ok                    |
| PATCH      | `api/v1/legalizacoes/{legalizacaoId}/etapas/{id}/status` (tenant) | Sim   | Pro           | Sim            | ok                    |
| POST       | `api/v1/legalizacoes/{legalizacaoId}/etapas/reorder` (tenant)     | Sim   | Pro           | Sim            | ok                    |
| DELETE     | `api/v1/legalizacoes/{legalizaco}` (tenant)                       | Sim   | Pro           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/legalizacoes/{legalizaco}` (tenant)                       | Sim   | Pro           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/legalizacoes/{legalizaco}` (tenant)                       | Sim   | Pro           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/legalizacoes/eligible-terrenos` (tenant)                  | Sim   | Pro           | Sim            | ok                    |

#### Respostas — legalizacoes + etapas (plano Pro)

```jsonc
// objeto Legalizacao (timestamps "Y-m-d H:i:s"; datas "YYYY-MM-DD")
{
  "id": 1, "terreno_id": 10,
  "terreno": { "id": 10, "nome": "...", "codigo_imovel": "...", "endereco": "...",
               "cidade": "...|null", "estado": "...|null", "status": "rótulo workflow" }, // quando carregado
  "responsavel_id": 2, "responsavel": { "id": 2, "name": "...", "email": "..." },          // quando carregado
  "nome": "...", "status": "em_andamento",
  "data_inicio_planejada": "YYYY-MM-DD|null", "data_fim_planejada": "YYYY-MM-DD|null",
  "data_inicio_prevista": "YYYY-MM-DD|null", "data_conclusao_prevista": "YYYY-MM-DD|null",
  "data_inicio_real": "YYYY-MM-DD|null", "data_fim_real": "YYYY-MM-DD|null",
  "percentual_concluido": 30, "progresso": 30,
  "custo_total_previsto": 50000.0, "observacoes": "string|null",
  "created_by": { "id": 1, "name": "..." }, "updated_by": { "id": 1, "name": "..." }, // quando carregados
  "etapas_count": 8, "total_etapas": 8,
  "etapas": [ /* LegalizacaoEtapa */ ],
  "pendencias": [ { "id": 1, "title": "...", "severity": "alta", "status": "aberta",
                    "is_critical": true, "due_date": "YYYY-MM-DD|null", "resolved_at": "ISO8601|null", "notes": "..." } ],
  "created_at": "2026-06-19 10:00:00", "updated_at": "2026-06-19 10:00:00"
}

// objeto LegalizacaoEtapa
{
  "id": 1, "legalizacao_id": 1, "parent_id": null, "titulo": "...", "descricao": "...",
  "ordem": 1, "status": "pendente",
  "inicio_planejado": "YYYY-MM-DD|null", "fim_planejado": "YYYY-MM-DD|null",
  "inicio_real": "YYYY-MM-DD|null", "fim_real": "YYYY-MM-DD|null",
  "percentual": 0, "responsavel_id": 2, "responsavel": { "id": 2, "name": "..." }, // quando carregado
  "cor": "#fff",
  "custos": [ { "tipo_custo": "taxa", "valor_custo": 100.0, "custo_pago": false } ],
  "tipo_custo": "taxa|Diversos|null", "valor_custo": 100.0, "custo_pago": false,
  "created_by": { "id": 1, "name": "..." }, "updated_by": { "id": 1, "name": "..." }, // quando carregados
  "dependencias": [ { "id": 1, "origem_id": 2, "destino_id": 1, "tipo": "FS" } ],       // quando carregado
  "created_at": "2026-06-19 10:00:00", "updated_at": "2026-06-19 10:00:00"
}
```

- **GET `legalizacoes`** · **GET `legalizacoes/eligible-terrenos`** — 200, paginação **formato A**.
- **POST `legalizacoes`** — 201 `{ "success": true, "data": { /* Legalizacao */ }, "message": "..." }`.
- **GET `legalizacoes/{legalizaco}`** — 200 `{ "success": true, "data": { /* Legalizacao + */ "etapas": [...], "dependencias": [...] } }`.
- **PUT `legalizacoes/{legalizaco}`** — 200 `{ "success": true, "data": { /* Legalizacao */ }, "message": "..." }`.
- **DELETE `legalizacoes/{legalizaco}`** — **204 No Content**.
- **POST `legalizacoes/{id}/recalcular-progresso`** — 200 `{ "success": true, "data": { /* Legalizacao */ }, "message": "..." }`.
- **POST `legalizacoes/{id}/sync-gantt`** — 200 `{ "success": true, "data": { /* Legalizacao + */ "etapas": [...], "dependencias": [...] } }`.
- **GET `legalizacoes/{legalizacaoId}/etapas`** — 200 `{ "success": true, "data": [ /* LegalizacaoEtapa */ ] }` (sem paginação).
- **POST etapas** — 201; **GET/PUT etapas/{id}** — 200 `{ "success": true, "data": { /* LegalizacaoEtapa */ } }`; **DELETE etapas/{id}** — **204**.
- **PATCH `etapas/{id}/status`** — 200 `{ "success": true, "data": { /* LegalizacaoEtapa */ } }`.
- **POST `etapas/reorder`** — 200 `{ "success": true, "data": [ /* LegalizacaoEtapa reordenadas */ ] }`.
- **404** `{ "code": "NOT_FOUND", "message": "Etapa não encontrada" }`; **500** `{ "code": "INTERNAL_ERROR" }` em falhas; **422** validação; **403** plano (Pro)/RBAC; **401** sem token.

### locale

| Método | Path                              | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ------- | --------------------------------- | ----- | ------------- | -------------- | --------------------- |
| PUT     | `api/v1/locale` (sigapp.com.br) | Sim   | N/A           | Não           | ok                    |
| PUT     | `api/v1/locale` (tenant)        | Sim   | N/A           | Sim            | ok                    |

#### Respostas — locale

- **PUT `locale`** (central e tenant) — 200 `{ "success": true, "data": { "locale": "pt-br" }, "message": "..." }`. **422** validação de `locale` (allowlist); **401** sem token.

### mobile

| Método   | Path                                                | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | --------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| POST      | `api/v1/mobile/devices` (tenant)                  | Sim   | N/A           | Sim            | ok                    |
| DELETE    | `api/v1/mobile/devices/{installationId}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD | `api/v1/mobile/notifications` (tenant)            | Sim   | N/A           | Sim            | ok                    |
| POST      | `api/v1/mobile/notifications/{id}/read` (tenant)  | Sim   | N/A           | Sim            | ok                    |

#### Respostas — mobile

```jsonc
// objeto MobileDeviceInstallation
{
  "id": 1, "installation_id": "uuid", "platform": "ios|android", "device_name": "...",
  "app_version": "1.0.0", "expo_push_token": "ExponentPushToken[...]|null",
  "last_seen_at": "ISO8601|null", "created_at": "ISO8601", "updated_at": "ISO8601"
}

// objeto MobileNotification
{
  "id": 1, "title": "...", "body": "...", "type": "...", "entity_type": "terreno|null",
  "entity_id": 10, "tenant_slug": "x", "target_route": "/terrenos/10",
  "payload": { /* json livre */ }, "read_at": "ISO8601|null", "sent_at": "ISO8601|null",
  "created_at": "ISO8601"
}
```

- **POST `mobile/devices`** — 200 `{ "success": true, "data": { /* MobileDeviceInstallation */ }, "message": "..." }` (registro idempotente).
- **DELETE `mobile/devices/{installationId}`** — **204 No Content**.
- **GET `mobile/notifications`** — 200, paginação **formato A** (itens MobileNotification).
- **POST `mobile/notifications/{id}/read`** — 200 `{ "success": true, "data": { /* MobileNotification com read_at */ }, "message": "..." }`. **404** `{ "code": "NOT_FOUND", "message": "Notificação não encontrada" }`.
- **422** validação; **401** sem token.

### modules

| Método   | Path                        | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | --------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/modules` (tenant) | Sim   | N/A           | Sim            | ok                    |

#### Respostas — modules

- **GET `modules`** — 200 `{ "success": true, "data": [ /* setores com módulos */ ], "message": "..." }`. Cada item:

```jsonc
{
  "sector": { "slug": "comercial", "label": "Comercial", "order": 1 },
  "modules": [
    {
      "slug": "terrenos", "name": "Terrenos", "icon": "...", "description": "...",
      "order": 1, "active": true,
      "submodules": [ { "slug": "viabilidades", "label": "Viabilidades" } ]
    }
  ]
}
```

- **401** sem token.

### municipios

| Método   | Path                                                     | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | -------------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/municipios/{ibge_codigo}/dados-sidra` (tenant) | Sim   | N/A           | Sim            | ok                    |

#### Respostas — municipios

- **GET `municipios/{ibge_codigo}/dados-sidra`** — 200 `{ "success": true, "data": { /* indicadores SIDRA/IBGE do município */ } }` (cacheado). **422** `{ "success": false, "error": { "code": "INVALID_CODE", "message": "Código IBGE inválido." } }` quando o código não tem 7 dígitos. **401** sem token.

### negociacoes

| Método   | Path                                        | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/negociacoes` (tenant)             | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/negociacoes` (tenant)             | Sim   | Pro           | Sim            | ok                    |
| GET\|HEAD | `api/v1/negociacoes/{id}` (tenant)        | Sim   | Pro           | Sim            | ok                    |
| PUT       | `api/v1/negociacoes/{id}` (tenant)        | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/negociacoes/{id}/events` (tenant) | Sim   | Pro           | Sim            | ok                    |

#### Respostas — negociacoes (plano Pro)

```jsonc
// objeto Negociacao
{
  "id": 1, "terreno_id": 10, "status": "aberta", "proposal_value": 1000000.0,
  "business_model": "permuta|compra|...", "started_at": "ISO8601|null", "closed_at": "ISO8601|null",
  "notes": "string|null",
  "eventos": [
    { "id": 1, "event_type": "proposta_enviada", "payload": { /* json livre */ },
      "notes": "string|null", "user_id": 2, "happened_at": "ISO8601|null" }
  ],
  "contratos": [ /* Contrato — só quando carregado */ ]
}
```

- **GET `negociacoes`** — 200, paginação **formato A**.
- **POST `negociacoes`** — 201 `{ "success": true, "data": { /* Negociacao */ }, "message": "Negociação criada com sucesso" }`.
- **GET `negociacoes/{id}`** — 200 `{ "success": true, "data": { /* Negociacao */ } }`.
- **PUT `negociacoes/{id}`** — 200 `{ "success": true, "data": { /* Negociacao */ }, "message": "Negociação atualizada com sucesso" }`.
- **POST `negociacoes/{id}/events`** — 201 `{ "success": true, "data": { /* NegociacaoEvento */ }, "message": "Evento da negociação registrado com sucesso" }`.
- **404** model binding; **422** validação; **403** plano (Pro)/RBAC; **401** sem token.

### plans

| Método   | Path                                    | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | --------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/plans` (sigapp.com.br)        | Não  | N/A           | Não           | ok                    |
| GET\|HEAD | `api/v1/plans/{slug}` (sigapp.com.br) | Não  | N/A           | Não           | ok                    |

#### Respostas — plans (público)

```jsonc
// objeto Plan
{
  "id": 1, "name": "Básico", "slug": "basico", "description": "...",
  "price": 199.9, "formatted_price": "R$ 199,90", "trial_days": 14,
  "features": { /* mapa de features */ }, "limits": { "terrenos": 50, "users": 5 },
  "is_active": true, "is_popular": false, "sort_order": 1,
  "entitlements": [ /* EntitlementResource — só quando carregado */ ],
  "created_at": "ISO8601", "updated_at": "ISO8601"
}
```

- **GET `plans`** — 200 `{ "success": true, "data": [ /* Plan */ ], "message": "..." }`.
- **GET `plans/{slug}`** — 200 `{ "success": true, "data": { /* Plan */ }, "message": "..." }`. **404** `{ "code": "NOT_FOUND", "message": "Plano não encontrado" }`.

### premissas-viabilidade

| Método    | Path                                                              | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | ----------------------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/premissas-viabilidade` (tenant)                         | Sim   | Básico       | Sim            | ok                    |
| POST       | `api/v1/premissas-viabilidade` (tenant)                         | Sim   | Básico       | Sim            | ok                    |
| DELETE     | `api/v1/premissas-viabilidade/{premissas_viabilidade}` (tenant) | Sim   | Básico       | Sim            | ok                    |
| GET\|HEAD  | `api/v1/premissas-viabilidade/{premissas_viabilidade}` (tenant) | Sim   | Básico       | Sim            | ok                    |
| PUT\|PATCH | `api/v1/premissas-viabilidade/{premissas_viabilidade}` (tenant) | Sim   | Básico       | Sim            | ok                    |

#### Respostas — premissas-viabilidade

Recurso `PremissasViabilidade` (mesmo objeto em index/show/store/update). Campos numéricos são `number|null`; datas em `YYYY-MM-DD`; timestamps em `dd/mm/aaaa hh:mm:ss`.

```jsonc
// objeto PremissasViabilidade (modelo completo)
{
  "id": 1, "nome": "Padrão 2026", "perfil_financiamento": "string|null",
  "ativo": true, "versao": 1, "vigente_em": "YYYY-MM-DD|null", "encerrada_em": "YYYY-MM-DD|null",
  "pis_cofins": 0, "iss": 0, "outros_impostos": 0, "comissao": 0, "parceria_vgv": 0, "infra_nao_incidente": 0,
  "incorporacao": 0, "incorp_ri": 0, "incorp_entrega": 0, "incorp_ate_lancamento": 0, "obra_ate_lancamento": 0,
  "area_comum": 0, "contrapartidas": 0, "canteiro_mensal": 0, "mo_administrativa": 0, "seguros": 0,
  "assistencia_tecnica": 0, "despesas_comerciais": 0, "stand_vendas": 0, "mobilia_decoracao": 0,
  "gastos_mensais_stand": 0, "comissao_house_percentual": 0, "comissao_imobiliarias_percentual": 0,
  "percentual_vendas_house": 0, "construcao_stand_meses_antes_lancamento": 0, "ajuda_custo_gerente": 0,
  "ajuda_custo_gerente_regional": 0, "reembolso_logistica": 0, "bonus_cca": 0, "bonus_gerente": 0,
  "bonus_gerente_regional": 0, "bonus_credito": 0, "bonus_gestor_comercial": 0, "bonus_equipe_comercial": 0,
  "pagamento_comissao_venda": 0, "pagamento_comissao_desligamento": 0, "parcelamento_comissao_meses": 0,
  "parcelamento_comissao_terreno": 0, "marketing": 0, "marketing_lancamento": 0,
  "marketing_inicio_antes_lancamento": 0, "itbi_iptu": 0, "registro": 0, "custo_contratacao_cef": 0,
  "custo_medicao_cef": 0, "contratos_cef": 0, "produtos_cef": 0, "outras_despesas_financeiras": 0,
  "despesas_onerosas_bancos": 0, "prazo_obra": 0, "compra_terreno": 0, "porcentagem_lote_proprietario": 0,
  "taxa_juros_pj": 0, "carencia_pj_meses": 0, "amortizacao_pj_parcelas": 0, "percentual_antecipacao_pj": 0,
  "aporte_adicional_mensal": 0, "devolucao_aporte_percentual": 0, "distribuicao_lucros_percentual_obra": 0,
  "taxa_exposicao_aplicada": 0, "inadimplencia": 0, "atraso_meses": 0, "taxa_perda": 0,
  "meses_incorporacao": 0, "meses_lancamento": 0, "meses_entrega": 0, "meses_pos_obra": 0, "variavel_correcao": 0,
  "created_at": "dd/mm/aaaa hh:mm:ss", "updated_at": "dd/mm/aaaa hh:mm:ss"
}
```

- **GET** (lista) — 200, paginação formato B (coleção nativa) com os objetos acima.
- **GET/POST/PUT `.../{id}`** — `{ "success": true, "data": { /* objeto */ }, "message": "..." }` (200/201).
- **DELETE** — 200 `{ "success": true, "data": null, "message": "Premissas excluídas com sucesso" }`.
- **404** `{ "code": "NOT_FOUND", "message": "Premissas não encontradas" }`; **422** validação; **403** plano (Básico)/RBAC; **401** sem token.

### produtos

| Método    | Path                                           | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | ---------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/produtos` (tenant)                   | Sim   | Broker        | Sim            | ok                    |
| POST       | `api/v1/produtos` (tenant)                   | Sim   | Broker        | Sim            | ok                    |
| DELETE     | `api/v1/produtos/{produto}` (tenant)         | Sim   | Broker        | Sim            | ok                    |
| GET\|HEAD  | `api/v1/produtos/{produto}` (tenant)         | Sim   | Broker        | Sim            | ok                    |
| PUT\|PATCH | `api/v1/produtos/{produto}` (tenant)         | Sim   | Broker        | Sim            | ok                    |
| POST       | `api/v1/produtos/{produto}/restore` (tenant) | Sim   | Broker        | Sim            | ok                    |
| GET\|HEAD  | `api/v1/produtos/select` (tenant)            | Sim   | Broker        | Sim            | ok                    |

#### Respostas — produtos

Recurso `Produto`. `created_at`/`updated_at` em `dd/mm/aaaa hh:mm:ss`. Campos de custo/juros/correção são `number|null`.

```jsonc
// objeto Produto
{
  "id": 1, "name": "Casa 2Q", "description": "string|null", "image": "url|null",
  "private_area": number, "m2_cost": number, "infra_cost": number, "status": "active",
  "sinal": number, "parcela_obra": number, "parcela_posChave": number, "qtde_parcelas_posChave": number,
  "demanda_minCef": number, "defasagem_pgtoTerreno": number, "avaliacao_lotesCef": number,
  "juros_mensalSinal": number, "juros_mensalObra": number, "juros_mensalPosChave": number,
  "correcao_anualSinal": number, "correcao_anualObra": number, "correcao_anualPosChave": number,
  "curva_vendas": number, "assist_tecnica1": number, "assist_tecnica2": number, "assist_tecnica3": number,
  "assist_tecnica4": number, "assist_tecnica5": number, "meses_inicioConstrucao": number,
  "porcentagem_ConstrucaoStand": number,
  "created_at": "dd/mm/aaaa hh:mm:ss", "updated_at": "dd/mm/aaaa hh:mm:ss"
}
```

- **GET `produtos`** — 200, paginação **formato B + extras no topo** (`message`, `current_page`, `last_page`, `total`, `per_page` duplicados na raiz).
- **GET `produtos/select`** — 200 `{ "success": true, "data": [ /* projeção enxuta para combos (id + rótulo) */ ], "message": "..." }`.
- **GET/POST/PUT `produtos/{produto}`** — `{ "success": true, "data": { /* objeto */ }, "message": "..." }` (200/201).
- **POST `produtos/{produto}/restore`** — 200 `{ "success": true, "data": { /* objeto */ }, "message": "..." }`; se já ativo, `data:null` + mensagem "O produto já está ativo".
- **DELETE** — 200 `{ "success": true, "data": null, "message": "Produto excluído com sucesso" }`.
- **404** `{ "code": "NOT_FOUND", "message": "Produto não encontrado" }`; **422** validação; **403** plano (Broker)/RBAC; **401** sem token.

### projetos

| Método    | Path                                                     | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | -------------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/projetos` (tenant)                             | Sim   | Pro           | Sim            | ok                    |
| POST       | `api/v1/projetos` (tenant)                             | Sim   | Pro           | Sim            | ok                    |
| POST       | `api/v1/projetos/{id}/cancelar` (tenant)               | Sim   | Pro           | Sim            | ok                    |
| POST       | `api/v1/projetos/{id}/marcar-pronto-registro` (tenant) | Sim   | Pro           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/projetos/{projeto}` (tenant)                   | Sim   | Pro           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/projetos/{projeto}` (tenant)                   | Sim   | Pro           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/projetos/eligible-terrenos` (tenant)           | Sim   | Pro           | Sim            | ok                    |

#### Respostas — projetos (plano Pro)

```jsonc
// objeto Projeto
{
  "id": 1, "nome": "...", "terreno_id": 10, "responsavel_id": 2, "status": "em_andamento",
  "pronto_para_registro_em": "ISO8601|null",
  "responsavel": { "id": 2, "name": "...", "email": "..." },            // quando carregado
  "terreno": { "id": 10, "nome": "...", "status": "rótulo do workflow" }, // quando carregado
  "pronto_para_registro_por_user": { "id": 3, "name": "...", "email": "..." }, // quando carregado
  "created_at": "ISO8601", "updated_at": "ISO8601"
}
```

- **GET `projetos`** · **GET `projetos/eligible-terrenos`** — 200, paginação **formato A**.
- **POST `projetos`** — 201 `{ "success": true, "data": { /* Projeto */ }, "message": "..." }`. **422** `{ "code": "CREATE_ERROR", "message": "<motivo>" }`.
- **GET/PUT `projetos/{projeto}`** — 200 `{ "success": true, "data": { /* Projeto */ } [, "message"] }`.
- **POST `projetos/{id}/marcar-pronto-registro`** — 200 `{ "success": true, "data": { /* Projeto */ }, "message": "..." }`. **422** `{ "code": "MARK_READY_ERROR" }`.
- **POST `projetos/{id}/cancelar`** — 200 `{ "success": true, "data": { /* Projeto */ }, "message": "..." }`.
- **404** `{ "code": "NOT_FOUND", "message": "Projeto não encontrado" }`; **500** `{ "code": "INTERNAL_ERROR" }` em falhas inesperadas; **422** validação; **403** plano (Pro)/RBAC; **401** sem token.

### proprietarios

| Método    | Path                                             | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | ------------------------------------------------ | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/proprietarios` (tenant)                | Sim   | N/A           | Sim            | ok                    |
| POST       | `api/v1/proprietarios` (tenant)                | Sim   | N/A           | Sim            | ok                    |
| DELETE     | `api/v1/proprietarios/{proprietario}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/proprietarios/{proprietario}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/proprietarios/{proprietario}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/proprietarios/select` (tenant)         | Sim   | N/A           | Sim            | MÉDIO: rota aponta para método inexistente `proprietariosForSelect` → 500 |

#### Respostas — proprietarios

Recurso `Proprietario` (timestamps `Y-m-d H:i:s`):

```jsonc
// objeto Proprietario
{
  "id": 1, "terreno_id": 10, "nome": "...", "rg": "...|null", "cpf_cnpj": "...|null",
  "nascimento": "YYYY-MM-DD|null", "tipo_pessoa": "fisica|juridica", "estado_civil": "...|null",
  "nacionalidade": "...|null", "profissao": "...|null", "porcentagem_terreno": number,
  "email": "...|null", "telefone": "...|null", "endereco": "...|null", "cidade": "...|null",
  "estado": "...|null", "cep": "...|null",
  "conjuge": "...|null", "conjuge_rg": "...|null", "conjuge_nascimento": "YYYY-MM-DD|null",
  "conjuge_cpf_cnpj": "...|null", "observacoes": "...|null",
  "created_by": { /* UserResource — só quando carregado */ },
  "updated_by": { /* UserResource — só quando carregado */ },
  "cpf_cnpj_formatado": "...", "conjuge_cpf_cnpj_formatado": "...", "telefone_formatado": "...",
  "created_at": "2026-06-19 10:00:00", "updated_at": "2026-06-19 10:00:00"
}
```

- **GET `proprietarios`** — 200, paginação **formato B puro** (`data`+`links`+`meta`).
- **POST** — 201 `{ "success": true, "data": { /* objeto */ }, "message": "Proprietário criado com sucesso!" }`.
- **GET/PUT `.../{proprietario}`** — 200 `{ "success": true, "data": { /* objeto */ } [, "message"] }`.
- **DELETE** — 200 `{ "success": true, "message": "Proprietário removido com sucesso!" }`.
- **GET `proprietarios/select`** — ⚠️ **rota quebrada**: aponta para `ProprietariosController::proprietariosForSelect`, método inexistente → **500** `{ "code": "INTERNAL_ERROR" }` (ver Achados). O frontend não deve depender deste endpoint até correção.
- **404** model binding; **422** validação; **403** RBAC; **401** sem token.

### regionais

| Método    | Path                                     | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | ---------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/regionais` (tenant)            | Sim   | Broker        | Sim            | ok                    |
| POST       | `api/v1/regionais` (tenant)            | Sim   | Broker        | Sim            | ok                    |
| DELETE     | `api/v1/regionais/{regionai}` (tenant) | Sim   | Broker        | Sim            | ok                    |
| GET\|HEAD  | `api/v1/regionais/{regionai}` (tenant) | Sim   | Broker        | Sim            | ok                    |
| PUT\|PATCH | `api/v1/regionais/{regionai}` (tenant) | Sim   | Broker        | Sim            | ok                    |
| GET\|HEAD  | `api/v1/regionais/select` (tenant)     | Sim   | Broker        | Sim            | ok                    |

#### Respostas — regionais

Recurso `Regional`:

```jsonc
// objeto Regional
{
  "id": 1, "nome": "...", "estado": "SP", "cidade": "...", "endereco": "...|null",
  "numero": "...|null", "telefone": "...|null", "celular": "...|null", "observacoes": "...|null",
  "responsavel": { /* UserResource — só quando carregado */ },
  "created_by": { /* UserResource — só quando carregado */ },
  "updated_by": { /* UserResource — só quando carregado */ }
}
```

- **GET `regionais`** — 200, paginação **formato B + extras no topo** (suporta `?q=` busca, `?per_page=`).
- **GET `regionais/select`** — 200 `{ "success": true, "data": [ { "id": 1, "nome": "..." } ], "message": "Regionais recuperadas com sucesso" }`.
- **GET/POST/PUT `.../{regionai}`** — `{ "success": true, "data": { /* objeto */ }, "message": "..." }` (200/201).
- **DELETE** — 200 `{ "success": true, "data": null, "message": "Regional excluída com sucesso" }`.
- **404** `{ "code": "NOT_FOUND", "message": "Regional não encontrada" }`; **422** validação; **403** plano (Broker)/RBAC; **401** sem token.

### signup

| Método   | Path                                                 | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados                                                 |
| --------- | ---------------------------------------------------- | ----- | ------------- | -------------- | --------------------------------------------------------------------- |
| POST      | `api/v1/signup` (sigapp.com.br)                    | Não  | N/A           | Não           | MÉDIO: trial repetível; sem ledger por identidade/meio de pagamento |
| GET\|HEAD | `api/v1/signup/{sessionId}/status` (sigapp.com.br) | Não  | N/A           | Não           | ok: session Stripe é segredo de alta entropia e vínculo é validado |

#### Respostas — signup (público)

- **POST `signup`** — 200 `{ "success": true, "data": { "checkout_url": "https://checkout.stripe.com/...", "tenant_id": "uuid", "session_id": "cs_...", "tenant_slug": "minha-empresa" }, "message": "CHECKOUT_TENANT_CREATED_SUCCESSFULLY" }`. O frontend redireciona para `checkout_url`. Erros: **404** `PLAN_NOT_FOUND`; **422** validação (incl. `{ "errors": { "slug": [...] } }` para subdomínio indisponível); **409** `SUBDOMAIN_RESERVED`; **500** `SIGNUP_ERROR`.
- **GET `signup/{sessionId}/status`** — 200 `{ "success": true, "data": { "status": "active|pending|...", "payment_status": "paid|unpaid", "is_ready": true, "tenant_slug": "minha-empresa" }, "message": "..." }` (polling pós-checkout). **404** `SESSION_NOT_FOUND` (sessão inválida ou não vinculada ao tenant).

### start

| Método   | Path                      | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/start` (tenant) | Sim   | N/A           | Sim            | ok                    |

#### Respostas — start

Bootstrap do app após login. **GET `start`** — 200 `{ "success": true, "data": { ... }, "message": "..." }`:

```jsonc
{
  "tenant": { /* TenantResource — ver tenant (GET) — ou null */ },
  "user": { /* UserResource — ver auth/me — ou null */ },
  "modules": [ /* mesmos setores/módulos de GET modules */ ]
}
```

- **401** sem token.

### tenant

| Método   | Path                       | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | -------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/tenant` (tenant) | Sim   | N/A           | Sim            | ok                    |

#### Respostas — tenant (GET)

**GET `tenant`** — 200 `{ "success": true, "data": { /* TenantResource */ }, "message": "..." }`:

```jsonc
// objeto TenantResource
{
  "id": "uuid", "name": "...", "slug": "...", "status": "active",
  "plan": { /* PlanResource — só quando carregado (sempre carregado aqui) */ },
  "trial_ends_at": "ISO8601|null", "on_trial": false, "is_active": true,
  "setup_completed_at": "ISO8601|null", "created_at": "ISO8601"
}
```

- **403** RBAC (`viewAny` Terreno); **401** sem token.

### tenant-admin

| Método    | Path                                                           | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | -------------------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/tenant-admin/departments` (tenant)                   | Sim   | N/A           | Sim            | ok                    |
| POST       | `api/v1/tenant-admin/departments` (tenant)                   | Sim   | N/A           | Sim            | ok                    |
| DELETE     | `api/v1/tenant-admin/departments/{department}` (tenant)      | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/tenant-admin/departments/{department}` (tenant)      | Sim   | N/A           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/tenant-admin/departments/{department}` (tenant)      | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/tenant-admin/departments/select` (tenant)            | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/tenant-admin/permissions` (tenant)                   | Sim   | N/A           | Sim            | ok                    |
| POST       | `api/v1/tenant-admin/permissions` (tenant)                   | Sim   | N/A           | Sim            | ok                    |
| DELETE     | `api/v1/tenant-admin/permissions/{permission}` (tenant)      | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/tenant-admin/permissions/{permission}` (tenant)      | Sim   | N/A           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/tenant-admin/permissions/{permission}` (tenant)      | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/tenant-admin/positions` (tenant)                     | Sim   | N/A           | Sim            | ok                    |
| POST       | `api/v1/tenant-admin/positions` (tenant)                     | Sim   | N/A           | Sim            | ok                    |
| DELETE     | `api/v1/tenant-admin/positions/{position}` (tenant)          | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/tenant-admin/positions/{position}` (tenant)          | Sim   | N/A           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/tenant-admin/positions/{position}` (tenant)          | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/tenant-admin/positions/select` (tenant)              | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/tenant-admin/roles` (tenant)                         | Sim   | N/A           | Sim            | ok                    |
| POST       | `api/v1/tenant-admin/roles` (tenant)                         | Sim   | N/A           | Sim            | ok                    |
| DELETE     | `api/v1/tenant-admin/roles/{role}` (tenant)                  | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/tenant-admin/roles/{role}` (tenant)                  | Sim   | N/A           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/tenant-admin/roles/{role}` (tenant)                  | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/tenant-admin/roles/select` (tenant)                  | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/tenant-admin/users` (tenant)                         | Sim   | N/A           | Sim            | ok                    |
| POST       | `api/v1/tenant-admin/users` (tenant)                         | Sim   | N/A           | Sim            | ok                    |
| PUT        | `api/v1/tenant-admin/users/{id}/module-permissions` (tenant) | Sim   | N/A           | Sim            | ok                    |
| DELETE     | `api/v1/tenant-admin/users/{user}` (tenant)                  | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/tenant-admin/users/{user}` (tenant)                  | Sim   | N/A           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/tenant-admin/users/{user}` (tenant)                  | Sim   | N/A           | Sim            | ok                    |

#### Respostas — tenant-admin

Objetos (timestamps ISO8601):

```jsonc
// Department
{ "id": 1, "name": "...", "description": "string|null", "active": true, "created_at": "ISO8601", "updated_at": "ISO8601" }
// Position
{ "id": 1, "name": "...", "description": "string|null", "level": 1, "active": true, "created_at": "ISO8601", "updated_at": "ISO8601" }
// Role (Admin)
{ "id": 1, "name": "Gerente", "guard_name": "tenant", "permissions": [ /* Permission */ ],
  "permissions_count": 12, "users_count": 3, "created_at": "ISO8601", "updated_at": "ISO8601" }
// Permission (Admin)
{ "id": 1, "name": "terrenos.viewer", "guard_name": "tenant",
  "roles": [ /* RoleSelect {id,name} */ ], "roles_count": 2, "created_at": "ISO8601", "updated_at": "ISO8601" }
// User — ver UserResource (tenant) em auth/me
```

- **departments / positions**: `index` → 200 paginação **formato A**; `select` → 200 `{ "success": true, "data": [ /* objeto */ ] }`; `show/store/update` → `{ "success": true, "data": { /* objeto */ }, "message": "..." }` (200/201); `destroy` → **204** ou **400** `{ "code": "...", "message": "..." }` (em uso).
- **roles**: `select` → 200 `{ "success": true, "data": [ /* {id,name} */ ] }`; `index` → 200 `{ "success": true, "data": [ /* Role */ ], "message": "Roles recuperadas com sucesso" }`; `show/store/update` → `{ "success": true, "data": { /* Role */ }, "message": "..." }`; `destroy` → **204**, **409** `{ "code": "CONFLICT", "message": "Não é possível excluir uma role atribuída a usuários" }`, ou **4xx** para role de sistema; **404** `{ "code": "NOT_FOUND", "message": "Role não encontrada" }`.
- **permissions**: `index` → 200 `{ "success": true, "data": [ /* Permission */ ] }` (sem paginação); `show/store/update` → `{ "success": true, "data": { /* Permission */ } }`; `destroy` → **204** ou **409**; **404** `{ "message": "Permissão não encontrada" }`.
- **users**: `index` → 200 paginação **formato A**; `show/store/update` → UserResource (`{ "success": true, "data": { /* User */ }, "message": "..." }`); `destroy` → **204**, **400** `{ "code": "CANNOT_DELETE_SELF" }` ou `{ "code": "LAST_TENANT_ADMIN" }`; `PUT {id}/module-permissions` → 200 `{ "success": true, "data": { /* User */ }, "message": "..." }`.
- **422** validação; **403** RBAC (`tenant.admin`); **401** sem token.

### tenant-status

| Método   | Path                                     | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ---------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/tenant-status` (sigapp.com.br) | Sim   | N/A           | Não           | ok                    |

#### Respostas — tenant-status

**GET `tenant-status`** (central) — 200 `{ "success": true, "data": { /* snapshot do TenantStatusService */ }, "message": "..." }`. Traz situação de assinatura/trial/conta do usuário central para roteamento pós-login. **401** sem token.

### tenant

| Método   | Path                                                        | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ----------------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| POST      | `api/v1/tenant/billing-portal` (tenant)                   | Sim   | N/A           | Sim            | ok                    |
| POST      | `api/v1/tenant/billing/coupon/redeem` (tenant)            | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD | `api/v1/tenant/billing/history` (tenant)                  | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD | `api/v1/tenant/billing/invoices/{invoiceId}` (tenant)     | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD | `api/v1/tenant/billing/invoices/{invoiceId}/pdf` (tenant) | Sim   | N/A           | Sim            | ok                    |
| POST      | `api/v1/tenant/billing/payment-method` (tenant)           | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD | `api/v1/tenant/billing/payment-status` (tenant)           | Sim   | N/A           | Sim            | ok                    |
| POST      | `api/v1/tenant/billing/retry-payment` (tenant)            | Sim   | N/A           | Sim            | ok                    |
| POST      | `api/v1/tenant/billing/setup-intent` (tenant)             | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD | `api/v1/tenant/subdomain-availability/{subdomain}`        | Não  | N/A           | Não           | ok                    |
| GET\|HEAD | `api/v1/tenant/subscription` (tenant)                     | Sim   | N/A           | Sim            | ok                    |
| POST      | `api/v1/tenant/subscription/swap` (tenant)                | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD | `api/v1/tenant/usage` (tenant)                            | Sim   | N/A           | Sim            | ok                    |

#### Respostas — tenant (billing/assinatura)

```jsonc
// objeto Invoice (dados do Stripe; lines só com ?include_lines=1)
{
  "id": "in_...", "number": "...", "status": "paid|open|...",
  "amount_due": 19990, "amount_paid": 19990, "amount_remaining": 0, "currency": "brl",
  "hosted_invoice_url": "https://invoice.stripe.com/...", "invoice_pdf": "https://...pdf",
  "created_at": 1718800000, "period_start": 1718800000, "period_end": 1721392000,
  "lines": [ /* só quando include_lines */ ]
}
```

- **GET `tenant/subscription`** — 200 `{ "success": true, "data": { "status": "active", "plan": { /* PlanResource|null */ }, /* ...snapshot Stripe (renova_em, cancel_at, etc.) */ }, "message": "..." }`.
- **POST `tenant/subscription/swap`** — 200 `{ "success": true, "data": { "plan": { /* PlanResource */ }, /* ...detalhes da troca */ }, "message": "..." }`. **404** `PLAN_NOT_FOUND`; **422** `PLAN_UNAVAILABLE`; **409** `NO_ACTIVE_SUBSCRIPTION` / `ALREADY_ON_THIS_PLAN`; **500** em falha Stripe.
- **GET `tenant/usage`** — 200 `{ "success": true, "data": { "metrics": { /* contadores */ }, "percentages": { /* uso % */ }, "approaching_limits": false }, "message": "..." }`.
- **GET `tenant/billing/history`** — 200 `{ "success": true, "data": { "data": [ /* Invoice */ ], /* has_more, etc. */ }, "message": "..." }` (lista do Stripe; note o `data` aninhado).
- **GET `tenant/billing/invoices/{invoiceId}`** — 200 `{ "success": true, "data": { /* Invoice */ }, "message": "..." }`. **404** `INVOICE_NOT_FOUND`.
- **GET `tenant/billing/invoices/{invoiceId}/pdf`** — **302 redirect** para o PDF hospedado no Stripe. **404** `INVOICE_NOT_FOUND`.
- **POST `tenant/billing/setup-intent`** — 200 `{ "success": true, "data": { "client_secret": "seti_..._secret_..." }, "message": "SETUP_INTENT_CREATED" }`. **409** `BILLING_NOT_CONFIGURED`; **500** `SETUP_INTENT_ERROR`.
- **POST `tenant/billing/payment-method`** — 200 `{ "success": true, "data": null, "message": "PAYMENT_METHOD_UPDATED" }`. **422** validação de `payment_method_id`; **500** `PAYMENT_METHOD_UPDATE_ERROR`.
- **GET `tenant/billing/payment-status`** — 200 `{ "success": true, "data": { /* snapshot de cobrança/dunning */ }, "message": "..." }`.
- **POST `tenant/billing/retry-payment`** — 200 `{ "success": true, "data": { /* resultado da retentativa */ }, "message": "..." }`. **409** `ACCOUNT_CANCELLED`; **4xx** com `code` em falha.
- **POST `tenant/billing/coupon/redeem`** — 200 `{ "success": true, "data": { /* desconto aplicado */ }, "message": "..." }`. Erros: **404** `COUPON_NOT_FOUND`; **409** `NO_ACTIVE_SUBSCRIPTION`; **422** `COUPON_EXPIRED` / `COUPON_FULLY_REDEEMED` / `COUPON_INACTIVE` / `COUPON_NOT_APPLICABLE`; **500** `COUPON_REDEEM_ERROR`.
- **POST `tenant/billing-portal`** — 200 `{ "success": true, "data": { "url": "https://billing.stripe.com/..." }, "message": "..." }`. **409** `BILLING_PORTAL_UNAVAILABLE`; **500** `BILLING_PORTAL_ERROR`.
- **GET `tenant/subdomain-availability/{subdomain}`** (público) — 200 `{ "success": true, "data": { "available": true, "normalized_subdomain": "minha-empresa" }, "message": "..." }`.
- **401** sem token (exceto subdomain-availability); **403** RBAC quando aplicável.

### terreno-produtos

| Método    | Path                                                        | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | ----------------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/terreno-produtos` (tenant)                        | Sim   | N/A           | Sim            | ok                    |
| POST       | `api/v1/terreno-produtos` (tenant)                        | Sim   | N/A           | Sim            | ok                    |
| DELETE     | `api/v1/terreno-produtos/{terreno_produto}` (tenant)      | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/terreno-produtos/{terreno_produto}` (tenant)      | Sim   | N/A           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/terreno-produtos/{terreno_produto}` (tenant)      | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/terreno-produtos/by-terreno/{terrenoId}` (tenant) | Sim   | N/A           | Sim            | ok                    |

#### Respostas — terreno-produtos

Recurso `TerrenoProduto` (associação):

```jsonc
// objeto TerrenoProduto
{
  "id": 1, "terreno_id": 10, "produto_id": 5,
  "unidades": number, "valor": number, "permuta": number, "pgto_por_lote": "string|null",
  "observacoes": "string|null",
  "produto": { /* ProdutoResource — só quando carregado */ },
  "terreno": { /* TerrenoResource — só quando carregado */ }
}
```

- **GET `terreno-produtos`** — 200, paginação **formato B + extras no topo**.
- **GET `terreno-produtos/by-terreno/{terrenoId}`** — 200 `{ "success": true, "data": [ /* objetos */ ], "message": "..." }`.
- **GET/POST/PUT `.../{terreno_produto}`** — `{ "success": true, "data": { /* objeto */ }, "message": "..." }` (200/201).
- **DELETE** — 200 `{ "success": true, "data": null, "message": "Associação terreno-produto excluída com sucesso" }`.
- **404** `{ "code": "NOT_FOUND", "message": "Associação terreno-produto não encontrada" }`; **422** validação; **403** RBAC; **401** sem token.

### terrenos

| Método    | Path                                                 | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados                                |
| ---------- | ---------------------------------------------------- | ----- | ------------- | -------------- | ---------------------------------------------------- |
| GET\|HEAD  | `api/v1/terrenos` (tenant)                         | Sim   | Broker        | Sim            | ok                                                   |
| POST       | `api/v1/terrenos` (tenant)                         | Sim   | Broker        | Sim            | ok                                                   |
| POST       | `api/v1/terrenos/{id}/export/check-list` (tenant)  | Sim   | Básico       | Sim            | MÉDIO: exceção interna exposta; payload é logado |
| GET\|HEAD  | `api/v1/terrenos/{id}/export/pdf-detalhe` (tenant) | Sim   | Básico       | Sim            | ok                                                   |
| GET\|HEAD  | `api/v1/terrenos/{id}/export/viabilidade` (tenant) | Sim   | Básico       | Sim            | ok                                                   |
| POST       | `api/v1/terrenos/{id}/import-kmz` (tenant)         | Sim   | Broker        | Sim            | ok                                                   |
| GET\|HEAD  | `api/v1/terrenos/{id}/informacoes` (tenant)        | Sim   | Broker        | Sim            | ok                                                   |
| POST       | `api/v1/terrenos/{id}/informacoes` (tenant)        | Sim   | Broker        | Sim            | ok                                                   |
| PUT        | `api/v1/terrenos/{id}/qualificacao` (tenant)       | Sim   | Broker        | Sim            | ok                                                   |
| POST       | `api/v1/terrenos/{id}/recalculate-area` (tenant)   | Sim   | Broker        | Sim            | ok                                                   |
| GET\|HEAD  | `api/v1/terrenos/{id}/timeline` (tenant)           | Sim   | Broker        | Sim            | ok                                                   |
| GET\|HEAD  | `api/v1/terrenos/{id}/workflow` (tenant)           | Sim   | Broker        | Sim            | ok                                                   |
| POST       | `api/v1/terrenos/{id}/workflow` (tenant)           | Sim   | Broker        | Sim            | ok                                                   |
| DELETE     | `api/v1/terrenos/{terreno}` (tenant)               | Sim   | Broker        | Sim            | ok                                                   |
| GET\|HEAD  | `api/v1/terrenos/{terreno}` (tenant)               | Sim   | Broker        | Sim            | ok                                                   |
| PUT\|PATCH | `api/v1/terrenos/{terreno}` (tenant)               | Sim   | Broker        | Sim            | ok                                                   |
| GET\|HEAD  | `api/v1/terrenos/export/excel` (tenant)            | Sim   | Broker        | Sim            | ok                                                   |
| GET\|HEAD  | `api/v1/terrenos/export/pdf` (tenant)              | Sim   | Básico       | Sim            | ok                                                   |
| GET\|HEAD  | `api/v1/terrenos/filter` (tenant)                  | Sim   | Broker        | Sim            | ok                                                   |
| DELETE     | `api/v1/terrenos/informacoes/{infoId}` (tenant)    | Sim   | Broker        | Sim            | ok                                                   |
| PUT        | `api/v1/terrenos/informacoes/{infoId}` (tenant)    | Sim   | Broker        | Sim            | ok                                                   |
| GET\|HEAD  | `api/v1/terrenos/select` (tenant)                  | Sim   | Broker        | Sim            | ok                                                   |

#### Respostas — terrenos

`TerrenoResource` é o objeto central do domínio. Escalares sempre presentes; relações só aparecem quando carregadas (`whenLoaded`). Timestamps `Y-m-d H:i:s`, datas `YYYY-MM-DD`, valores monetários/área como `number`.

```jsonc
// objeto Terreno — escalares
{
  "id": 1, "nome": "...", "responsavel_id": 2, "endereco": "...", "corretor_id": null,
  "estado": "SP", "cidade_code": "3550308", "cidade_nome": "São Paulo",  // cidade_nome só quando relação cidade carregada
  "polygon_coords": [ /* ... */ ], "static_map_url": "url|null",
  "area_calculada": number, "area_total": number, "area_declividade": number, "area_app": number,
  "area_util": number, "percentual_aproveitamento": number,
  "area_calculada_em": "ISO8601|null", "area_calculo_status": "string|null",
  "declividade_classificacao": "string|null", "declividade_classificacao_label": "string|null",
  "declividade_avaliacao": null, "declividade_impacto_custo": null,
  "declividade_percentual_maximo": number, "declividade_percentual_medio": number,
  "app_polygons": null, "steep_polygons": null,
  "workflow_stage": "string", "workflow_status_code": "string", "workflow_status_label": "string|null",
  "workflow_status_changed_at": "ISO8601|null", "workflow_reason_code": null, "workflow_reason_notes": null,
  "qualification_data": null, "qualification_completed_at": "ISO8601|null",
  "checklist": { /* checklist do LandWorkflowService */ },
  "regional_id": null, "cep": null, "bairro": null, "observacoes": null, "valor": number,
  "zona": null, "distrito": null, "operacao_urbana": null,
  "data_apresentacao": "YYYY-MM-DD|null", "data_negociacao": "YYYY-MM-DD|null", "data_opcao": "YYYY-MM-DD|null",
  "data_descarte": "YYYY-MM-DD|null", "data_contrato": "YYYY-MM-DD|null",
  "comprador_id": null, "created_by": 1, "updated_by": 1,
  "created_at": "2026-06-19 10:00:00", "updated_at": "2026-06-19 10:00:00", "deleted_at": null,
  // calculados
  "valor_formatado": "R$ 1.000.000,00|null", "area_formatada": "1.234,00 m²|null", "endereco_completo": "...",
  // contadores (presentes quando withCount aplicado)
  "terreno_produtos_count": null, "total_unidades": null, "vgv_total": null,
  "documentos_count": null, "viabilidades_count": null, "terreno_infos_count": null
}
```

Relações incluídas **apenas quando carregadas**: `responsavel` (User), `corretor_externo`, `regional`, `comprador` `{id,name,email}`, `created_by_user`/`updated_by_user` `{id,name}`, `proprietarios[]`, `contatos[]`, `terreno_produtos[]`, `cidade_dados`, `documentos[]`, `viabilidades[]`, `informacoes[]`, `viabilidade_atual`, `comite_atual`, `negociacao_atual`, `contrato_atual`, `legalizacao`, `tasks[]`, `comments[]`, `status_history[]`, `activities[]`.

- **GET `terrenos`** — 200, paginação **formato A**.
- **GET `terrenos/filter`** — 200, paginação **formato B** (respondWithPagination); em erro de filtro, JSON com mensagem.
- **POST `terrenos`** — 201 `{ "success": true, "data": { /* Terreno */ }, "message": "..." }`.
- **GET/PUT `terrenos/{terreno}`** — 200 `{ "success": true, "data": { /* Terreno */ } [, "message"] }`.
- **DELETE `terrenos/{terreno}`** — **204 No Content**.
- **GET `terrenos/select`** — 200 `{ "success": true, "data": [ /* projeção enxuta */ ] }`.
- **GET/POST `terrenos/{id}/informacoes`** — GET: `{ "success": true, "data": [ /* TerrenoInfo */ ] }`; POST: 201 com TerrenoInfo.
- **PUT/DELETE `terrenos/informacoes/{infoId}`** — PUT 200 TerrenoInfo; DELETE **204**.
- **GET/POST `terrenos/{id}/workflow`** — 200 `{ "success": true, "data": { /* estágio, status, checklist, transições */ } }`.
- **PUT `terrenos/{id}/qualificacao`** — 200 `{ "success": true, "data": { /* workflow atualizado */ } }`.
- **POST `terrenos/{id}/recalculate-area`** — 200 `{ "success": true, "data": { /* Terreno */ } }`; **422/4xx** `{ "code": "...", "message": "..." }` quando não recalculável.
- **POST `terrenos/{id}/import-kmz`** — 200 `{ "success": true, "data": { /* Terreno */ } }`; erro de arquivo → JSON `{ "message": "..." }` 4xx.
- **GET `terrenos/{id}/timeline`** — 200, **formato B** (coleção de TimelineEntry).
- **Exports** (`export/excel`, `export/pdf`, `export/pdf-detalhe`, `export/viabilidade`, `export/check-list`) — **stream binário** (XLSX/PDF). **404** `{ "message": "Terreno não encontrado" }`. ⚠️ `export/check-list` pode expor exceção interna (ver tabela/Achados).
- **404** model binding; **422** validação; **403** plano (Broker/Básico)/RBAC; **401** sem token.

### users

| Método   | Path                                 | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ------------------------------------ | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/users/for-select` (tenant) | Sim   | N/A           | Sim            | ok                    |

#### Respostas — users

- **GET `users/for-select`** — 200 `{ "success": true, "data": [ { "id": 1, "name": "..." } ], "message": "Usuários carregados com sucesso" }` (apenas `id` + `name`, não sensível, disponível a qualquer usuário autenticado). **401** sem token.

### viabilidades

| Método    | Path                                                        | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | ----------------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/viabilidades` (tenant)                            | Sim   | Básico       | Sim            | ok                    |
| POST       | `api/v1/viabilidades` (tenant)                            | Sim   | Básico       | Sim            | ok                    |
| POST       | `api/v1/viabilidades/{id}/aprovar` (tenant)               | Sim   | Básico       | Sim            | ok                    |
| POST       | `api/v1/viabilidades/{id}/ativar` (tenant)                | Sim   | Básico       | Sim            | ok                    |
| POST       | `api/v1/viabilidades/{id}/duplicate` (tenant)             | Sim   | Básico       | Sim            | ok                    |
| GET\|HEAD  | `api/v1/viabilidades/{id}/export-pdf` (tenant)            | Sim   | Básico       | Sim            | ok                    |
| POST       | `api/v1/viabilidades/{id}/gerar-dre` (tenant)             | Sim   | Básico       | Sim            | ok                    |
| POST       | `api/v1/viabilidades/{id}/recalcular` (tenant)            | Sim   | Básico       | Sim            | ok                    |
| POST       | `api/v1/viabilidades/{id}/reprovar` (tenant)              | Sim   | Básico       | Sim            | ok                    |
| POST       | `api/v1/viabilidades/{id}/restore` (tenant)               | Sim   | Básico       | Sim            | ok                    |
| POST       | `api/v1/viabilidades/{id}/solicitar-aprovacao` (tenant)   | Sim   | Básico       | Sim            | ok                    |
| DELETE     | `api/v1/viabilidades/{viabilidade}` (tenant)              | Sim   | Básico       | Sim            | ok                    |
| GET\|HEAD  | `api/v1/viabilidades/{viabilidade}` (tenant)              | Sim   | Básico       | Sim            | ok                    |
| PUT\|PATCH | `api/v1/viabilidades/{viabilidade}` (tenant)              | Sim   | Básico       | Sim            | ok                    |
| POST       | `api/v1/viabilidades/compare` (tenant)                    | Sim   | Básico       | Sim            | ok                    |
| GET\|HEAD  | `api/v1/viabilidades/for-select` (tenant)                 | Sim   | Básico       | Sim            | ok                    |
| GET\|HEAD  | `api/v1/viabilidades/terreno/{terrenoId}` (tenant)        | Sim   | Básico       | Sim            | ok                    |
| GET\|HEAD  | `api/v1/viabilidades/terreno/{terrenoId}/latest` (tenant) | Sim   | Básico       | Sim            | ok                    |

#### Respostas — viabilidades (plano Básico)

`ViabilidadeResource`. Campos numéricos são `number` (cast explícito; nunca `null` nos premissados); timestamps `Y-m-d H:i:s`. Bloco `auditoria` só aparece com `?include=auditoria`.

```jsonc
// objeto Viabilidade (modelo completo)
{
  "id": 1, "terreno_id": 10, "version": 1, "is_current": true,
  "parceria_vgv": 0, "compra_terreno": 0, "infra_nao_incidente": 0, "porcentagem_lote_proprietario": 0,
  "prazo_obra": 0, "prazo_lancamento": 0, "prazo_incorporacao": 0,
  "pis_cofins": 0, "iss": 0, "outros_impostos": 0, "comissao": 0, "incorporacao": 0, "area_comum": 0,
  "contrapartidas": 0, "canteiro_mensal": 0, "mo_administrativa": 0, "seguros": 0, "assistencia_tecnica": 0,
  "despesas_comerciais": 0, "stand_vendas": 0, "mobilia_decoracao": 0, "gastos_mensais_stand": 0,
  "comissao_house_percentual": 0, "comissao_imobiliarias_percentual": 0, "percentual_vendas_house": 0,
  "construcao_stand_meses_antes_lancamento": 0, "ajuda_custo_gerente": 0, "ajuda_custo_gerente_regional": 0,
  "reembolso_logistica": 0, "bonus_cca": 0, "bonus_gerente": 0, "bonus_gerente_regional": 0, "bonus_credito": 0,
  "bonus_gestor_comercial": 0, "bonus_equipe_comercial": 0, "pagamento_comissao_venda": 0,
  "pagamento_comissao_desligamento": 0, "parcelamento_comissao_meses": 0, "marketing": 0,
  "marketing_lancamento": 0, "marketing_inicio_antes_lancamento": 0, "itbi_iptu": 0, "registro": 0,
  "custo_contratacao_cef": 0, "custo_medicao_cef": 0, "contratos_cef": 0, "produtos_cef": 0,
  "outras_despesas_financeiras": 0, "despesas_onerosas_bancos": 0, "percentual_antecipacao_pj": 0,
  "aporte_adicional_mensal": 0, "devolucao_aporte_percentual": 0, "distribuicao_lucros_percentual_obra": 0,
  "taxa_exposicao_aplicada": 0, "perfil_financiamento": "cef",
  "status": "ativo|rascunho|...", "approval_status": "aprovada|pendente|...",
  "approval_requested_at": "ISO8601|null", "approval_decided_at": "ISO8601|null", "approval_notes": "string|null",
  "submitted_at": "ISO8601|null", "locked_at": "ISO8601|null",
  "created_at": "2026-06-19 10:00:00", "updated_at": "2026-06-19 10:00:00", "deleted_at": null,
  "resultados_dre": { /* resultados calculados, quando presentes */ },
  "terreno": { "id": 10, "nome": "...", "area": number } | null,
  "auditoria": { /* só com ?include=auditoria: created_by_user, updated_by_user, approval_decided_by_user, sections[], approvals[] */ }
}
```

- **GET `viabilidades`** — 200, paginação **formato A**. **500** `{ "code": "INTERNAL_ERROR" }` em falha.
- **POST `viabilidades`** — 201 `{ "success": true, "data": { /* Viabilidade */ } }`. **422** validação de domínio (`{ "errors": {...} }`).
- **GET/PUT `viabilidades/{viabilidade}`** — 200 `{ "success": true, "data": { /* Viabilidade */ } }`; PUT pode retornar **422**.
- **DELETE** — 200 `{ "success": true, "data": null, "message": "Viabilidade excluída com sucesso" }`.
- **POST `{id}/ativar` · `{id}/solicitar-aprovacao` · `{id}/aprovar` · `{id}/reprovar` · `{id}/recalcular` · `{id}/restore`** — 200 `{ "success": true, "data": { /* Viabilidade */ }, "message": "..." }`.
- **POST `{id}/duplicate`** — 201 `{ "success": true, "data": { /* Viabilidade nova */ } }`.
- **POST `{id}/gerar-dre`** — 200 `{ "success": true, "data": { /* dre_resultados */ } }`. **500** com mensagem em falha de cálculo.
- **POST `viabilidades/compare`** — 200 `{ "success": true, "data": { /* comparativo entre versões */ } }`.
- **GET `viabilidades/for-select`** — 200 `{ "success": true, "data": [ /* projeção enxuta (ViabilidadeSelect) */ ] }`.
- **GET `viabilidades/terreno/{terrenoId}`** — 200 `{ "success": true, "data": [ /* Viabilidade */ ] }`.
- **GET `viabilidades/terreno/{terrenoId}/latest`** — 200 `{ "success": true, "data": { /* Viabilidade */ } }`; **404** `{ "code": "NOT_FOUND", "message": "Nenhuma viabilidade encontrada para este terreno" }`.
- **GET `viabilidades/{id}/export-pdf`** — **stream PDF**.
- **403** plano (Básico)/RBAC; **401** sem token.

### webhook

| Método | Path                                      | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados                                                |
| ------- | ----------------------------------------- | ----- | ------------- | -------------- | -------------------------------------------------------------------- |
| POST    | `api/v1/webhook/stripe` (sigapp.com.br) | Não  | N/A           | Não           | ok: assinatura obrigatória fora de local/test, lock e idempotência |

### docs

| Método                                      | Path              | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| -------------------------------------------- | ----------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS | `docs`          | Não  | N/A           | Não           | ok                    |
| GET\|HEAD                                    | `docs/api`      | Não  | N/A           | Não           | ok                    |
| GET\|HEAD                                    | `docs/api.json` | Não  | N/A           | Não           | ok                    |

### registration

| Método   | Path             | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ---------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `registration` | Não  | N/A           | Não           | ok                    |

### resend

| Método | Path               | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ------- | ------------------ | ----- | ------------- | -------------- | --------------------- |
| POST    | `resend/webhook` | Não  | N/A           | Não           | ok                    |

### sanctum

| Método   | Path                    | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ----------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `sanctum/csrf-cookie` | Não  | N/A           | Não           | ok                    |

### storage

| Método   | Path               | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ------------------ | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `storage/{path}` | Não  | N/A           | Não           | ok                    |
| PUT       | `storage/{path}` | Não  | N/A           | Não           | ok                    |

### stripe

| Método   | Path                    | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ----------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `stripe/payment/{id}` | Não  | N/A           | Não           | ok                    |

### tenancy

| Método   | Path                                | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ----------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `tenancy/assets/{path?}` (tenant) | Não  | N/A           | Sim            | ok                    |

### up

| Método   | Path   | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ------ | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `up` | Não  | N/A           | Não           | ok                    |
