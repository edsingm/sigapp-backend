# Review técnico completo do backend — 2026-06-18

## Escopo e método

Review somente leitura de todo o código próprio inventariado em `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `resources/`, `tests/` e `scripts`: 953 arquivos, 923 PHP e 77.813 linhas PHP. Todas as 519 entradas de `route:list` foram inspecionadas e colapsadas por método/path/action/middleware em 333 contratos únicos na tabela abaixo. Binários e artefatos gerados foram avaliados como artefatos; `vendor/` não recebeu review linha a linha, mas versões, advisories e os trechos relevantes de Sanctum/Cashier foram verificados.

Verificações executadas:

- `php artisan test`: **584 testes, 1.979 assertions, todos passando**.
- PHPStan nível 8: **sem erros** (revalidado em 2026-06-18 com PHP 8.4 e `--memory-limit=1G`).
- `composer audit --locked`: **sem advisories**.
- Inventário de secrets rastreados: nenhum token/chave privada hardcoded identificado pelos padrões usados.
- Migrations: todas declaram `down()`, mas duas possuem rollback vazio/não automático.

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

**[MÉDIO]** — `app/Http/Controllers/Api/V1/Tenant/DashboardController.php:461` e `TerrenosExportController.php:118`

> Respostas 500 retornam `getMessage()` sem condicionar a ambiente; o checklist também grava payload do usuário e stack trace em log.
> Impacto: vazamento de SQL, paths, nomes internos e dados inseridos no checklist.
> Correção sugerida: resposta genérica em produção, correlation ID e detalhes somente em log sanitizado; remover payload bruto/trace fora de canal protegido.

**[MÉDIO]** — `config/cors.php:9` e `bootstrap/app.php:38`

> CORS com credenciais aceita `http` e `https` em qualquer subdomínio de `APP_DOMAIN`; não há middleware de HSTS/CSP/X-Frame/X-Content-Type/Referrer-Policy no app.
> Impacto: se proxy/CDN não aplicar HTTPS e headers, um subdomínio comprometido ou servido em HTTP amplia ataques com credenciais e clickjacking/MIME sniffing.
> Correção sugerida: em produção aceitar apenas `https://*.domínio`, allowlist quando possível e adicionar headers no edge ou middleware. Tradeoff: CSP estrita exige inventário de scripts/assets; HSTS com `includeSubDomains` exige todos os subdomínios em HTTPS.

**[MÉDIO]** — `app/Http/Controllers/Api/V1/Tenant/AiScoringController.php:67` e `app/Services/AiScoringService.php:150`

> Um viewer pode recalcular todos os scores, síncrono, carregando todos os terrenos e executando um upsert por item. O GET individual também aceita `?recalculate=true` e escreve.
> Impacto: abuso de CPU/DB e mutação por perfil de leitura; GET deixa de ser idempotente.
> Correção sugerida: exigir permissão editor, mover recálculo para job paginado/único e remover escrita do GET.

**[MÉDIO]** — `app/Services/Tenant/AiMonitorService.php:131`

> `tasks` é eager-loaded, mas o loop chama `$t->tasks()->get()` novamente.
> Impacto: N+1 de uma query por terreno no monitor; combinado ao limite 200 degrada o endpoint.
> Correção sugerida: iterar `$t->tasks` já carregado.

**[MÉDIO]** — `app/Services/Signup/TenantSignupService.php:72`

> Não há ledger central de trial por email, organização ou payment method. Novo slug/tenant gera novo customer e novo trial.
> Impacto: repetição de trials com identidades/slugs novos.
> Correção sugerida: registrar elegibilidade de trial de forma central e aplicar regra de negócio; tradeoff entre email verificado (menos atrito) e fingerprint/payment method (mais forte, maior impacto de privacidade/suporte).

### Severidade baixa

**[BAIXO]** — `app/Jobs/CalculateUsableAreaJob.php:41`

> `uniqueId()` contém apenas o ID local do terreno. IDs se repetem entre schemas; dependendo do momento em que o lock é prefixado pelo bootstrap de cache, tenants podem colidir.
> Impacto: cálculo de área de um tenant pode ser suprimido pelo job de outro.
> Correção sugerida: incluir tenant ID no unique ID e adicionar teste com dois schemas. Marcado como risco porque o prefixo efetivo do lock deve ser verificado no worker real.

**[BAIXO]** — `database/migrations/tenant/2026_03_11_000002_backfill_workflow_and_versions.php:111`

> `down()` é vazio; a migration seguinte também declara rollback não automático.
> Impacto: rollback não restaura estado, contrariando o contrato operacional do projeto.
> Correção sugerida: documentar como irreversível com estratégia de restore/backup ou implementar reversão quando os dados originais puderem ser preservados.

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
- Trial: Stripe controla o período, mas a elegibilidade de repetição não é centralizada.
- Cartão: dados completos não passam pelo backend; usa Checkout, SetupIntent e payment method IDs. Só brand/last4/expiração são lidos.
- Evento faltante relevante: `invoice.payment_action_required` não tem handler próprio apesar de existir template de email.

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
- PDFs gerados pela IA agora passam por sanitização com `HTMLPurifier`, bloqueio de esquemas URI e `disableJavascript()` antes do render.
- Logout e logout-all agora invalidam sessão stateful quando não há `PersonalAccessToken`; refresh rejeita tokens não refreshables sem chamar `delete()`.
- Dependências auditadas foram atualizadas (`laravel/framework` 13.16.1, `guzzlehttp/psr7` 2.12.1, `mtdowling/jmespath.php` 2.9.1) e `composer audit --locked` está limpo.
- Operações compostas de signup, workflow, legalização, projeto, comitê, negociação e viabilidade usam transações.
- 584 testes passam, incluindo auth, billing/webhook, tenancy, ACL, recursos e fluxos de negócio; PHPStan nível 8 também passa sem erros.
- Nenhum uso de command execution/eval ou SQL raw concatenando input foi confirmado.
- Todas as migrations possuem método `down()` declarado; a maioria implementa rollback funcional.

## Resumo executivo — top 5

1. CORS com credenciais ainda aceita `http` e `https` em subdomínios, e o app não define HSTS/CSP/X-Frame/X-Content-Type/Referrer-Policy localmente.
2. Um viewer ainda pode recalcular scores em massa e disparar escrita síncrona no GET com `?recalculate=true`.
3. O monitor de IA ainda faz N+1 ao recarregar `tasks()` dentro do loop apesar do eager loading.
4. Não há ledger central de trial por email/organização/payment method, permitindo repetição de trials com novas identidades.
5. Respostas 500 ainda expõem `getMessage()` em alguns fluxos tenant/export, com risco de vazamento de detalhes internos.

## Tabela completa de endpoints

A tabela tem 333 contratos únicos. Rotas centrais repetidas nos quatro domínios configurados foram colapsadas e exibem o domínio canônico; entradas tenant aparecem com `(tenant)`. “Plano mínimo” é derivado da matriz seed atual; `N/A` significa que a rota não usa feature gate de plano.

### raiz

| Método   | Path  | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ----- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `/` | Não  | N/A           | Não           | ok                    |

### api

| Método   | Path                    | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ----------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/health` (tenant) | Sim   | N/A           | Sim            | ok                    |

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
| GET\|HEAD | `api/v1/ai/scoring/{terreno_id}` (tenant)             | Sim   | Master        | Sim            | MÉDIO: query recalculate permite escrita em GET para viewer         |
| GET\|HEAD | `api/v1/ai/scoring/ranking` (tenant)                  | Sim   | Master        | Sim            | ok                                                                   |
| POST      | `api/v1/ai/scoring/recalculate` (tenant)              | Sim   | Master        | Sim            | MÉDIO: viewer dispara escrita síncrona e sem limite de lote        |
| POST      | `api/v1/ai/sig-ai` (tenant)                           | Sim   | Master        | Sim            | ALTO: tools consultam módulos Pro usando só permissão de terrenos |

### auth

| Método   | Path                                            | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados                                      |
| --------- | ----------------------------------------------- | ----- | ------------- | -------------- | ---------------------------------------------------------- |
| POST      | `api/v1/auth/exchange-ticket` (tenant)        | Não  | N/A           | Sim            | ok                                                         |
| POST      | `api/v1/auth/login` (sigapp.com.br)           | Não  | N/A           | Não           | ok                                                         |
| POST      | `api/v1/auth/login` (tenant)                  | Não  | N/A           | Sim            | ok                                                         |
| POST      | `api/v1/auth/logout` (sigapp.com.br)          | Sim   | N/A           | Não           | MÉDIO: fluxo quebra/não encerra sessão Sanctum stateful |
| POST      | `api/v1/auth/logout` (tenant)                 | Sim   | N/A           | Sim            | MÉDIO: fluxo quebra/não encerra sessão Sanctum stateful |
| POST      | `api/v1/auth/logout-all` (sigapp.com.br)      | Sim   | N/A           | Não           | MÉDIO: fluxo quebra/não encerra sessão Sanctum stateful |
| POST      | `api/v1/auth/logout-all` (tenant)             | Sim   | N/A           | Sim            | MÉDIO: fluxo quebra/não encerra sessão Sanctum stateful |
| GET\|HEAD | `api/v1/auth/me` (sigapp.com.br)              | Sim   | N/A           | Não           | ok                                                         |
| GET\|HEAD | `api/v1/auth/me` (tenant)                     | Sim   | N/A           | Sim            | ok                                                         |
| PUT       | `api/v1/auth/me` (tenant)                     | Sim   | N/A           | Sim            | ok                                                         |
| POST      | `api/v1/auth/password/forgot` (sigapp.com.br) | Não  | N/A           | Não           | ok                                                         |
| POST      | `api/v1/auth/password/forgot` (tenant)        | Não  | N/A           | Sim            | ok                                                         |
| POST      | `api/v1/auth/password/reset` (sigapp.com.br)  | Não  | N/A           | Não           | ok                                                         |
| POST      | `api/v1/auth/password/reset` (tenant)         | Não  | N/A           | Sim            | ok                                                         |
| POST      | `api/v1/auth/refresh` (sigapp.com.br)         | Sim   | N/A           | Não           | MÉDIO: fluxo quebra/não encerra sessão Sanctum stateful |
| POST      | `api/v1/auth/refresh` (tenant)                | Sim   | N/A           | Sim            | MÉDIO: fluxo quebra/não encerra sessão Sanctum stateful |
| POST      | `api/v1/auth/select-tenant` (sigapp.com.br)   | Não  | N/A           | Não           | ok                                                         |

### blog

| Método   | Path                                       | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ------------------------------------------ | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/blog` (sigapp.com.br)            | Não  | N/A           | Não           | ok                    |
| GET\|HEAD | `api/v1/blog/{slug}` (sigapp.com.br)     | Não  | N/A           | Não           | ok                    |
| GET\|HEAD | `api/v1/blog/categories` (sigapp.com.br) | Não  | N/A           | Não           | ok                    |

### cidades

| Método   | Path                                 | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ------------------------------------ | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/cidades/{estado}` (tenant) | Sim   | Broker        | Sim            | ok                    |
| GET\|HEAD | `api/v1/cidades/buscar` (tenant)   | Sim   | Broker        | Sim            | ok                    |
| GET\|HEAD | `api/v1/cidades/dados` (tenant)    | Sim   | Broker        | Sim            | ok                    |
| GET\|HEAD | `api/v1/cidades/estados` (tenant)  | Sim   | Broker        | Sim            | ok                    |

### comite

| Método   | Path                                               | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | -------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/comite` (tenant)                         | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/comite` (tenant)                         | Sim   | Pro           | Sim            | ok                    |
| GET\|HEAD | `api/v1/comite/{id}` (tenant)                    | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/comite/{id}/decision` (tenant)           | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/comite/{id}/department-reviews` (tenant) | Sim   | Pro           | Sim            | ok                    |

### consent-log

| Método | Path                                   | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ------- | -------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| POST    | `api/v1/consent-log` (sigapp.com.br) | Não  | N/A           | Não           | ok                    |

### contratos

| Método   | Path                                    | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | --------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/contratos` (tenant)           | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/contratos` (tenant)           | Sim   | Pro           | Sim            | ok                    |
| GET\|HEAD | `api/v1/contratos/{id}` (tenant)      | Sim   | Pro           | Sim            | ok                    |
| PUT       | `api/v1/contratos/{id}` (tenant)      | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/contratos/{id}/sign` (tenant) | Sim   | Pro           | Sim            | ok                    |

### corretores-externos

| Método    | Path                                                         | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | ------------------------------------------------------------ | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/corretores-externos` (tenant)                      | Sim   | N/A           | Sim            | ok                    |
| POST       | `api/v1/corretores-externos` (tenant)                      | Sim   | N/A           | Sim            | ok                    |
| DELETE     | `api/v1/corretores-externos/{corretores_externo}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/corretores-externos/{corretores_externo}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/corretores-externos/{corretores_externo}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/corretores-externos/select` (tenant)               | Sim   | N/A           | Sim            | ok                    |

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
| GET\|HEAD | `api/v1/dashboard/terrenos-responsavel` (tenant)          | Sim   | Básico       | Sim            | MÉDIO: exceção interna exposta em resposta 500 |
| GET\|HEAD | `api/v1/dashboard/top-cidades` (tenant)                   | Sim   | Básico       | Sim            | ok                                                |
| GET\|HEAD | `api/v1/dashboard/unidades-fechadas-anual` (tenant)       | Sim   | Master        | Sim            | ok                                                |
| GET\|HEAD | `api/v1/dashboard/vgv-anual` (tenant)                     | Sim   | Master        | Sim            | ok                                                |

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

### health

| Método   | Path                                      | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados                                        |
| --------- | ----------------------------------------- | ----- | ------------- | -------------- | ------------------------------------------------------------ |
| GET\|HEAD | `api/v1/health`                         | Não  | N/A           | Não           | BAIXO: health público executa checks externos; há throttle |
| GET\|HEAD | `api/v1/health/details` (sigapp.com.br) | Sim   | N/A           | Não           | ok                                                           |

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

### locale

| Método | Path                              | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ------- | --------------------------------- | ----- | ------------- | -------------- | --------------------- |
| PUT     | `api/v1/locale` (sigapp.com.br) | Sim   | N/A           | Não           | ok                    |
| PUT     | `api/v1/locale` (tenant)        | Sim   | N/A           | Sim            | ok                    |

### mobile

| Método   | Path                                                | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | --------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| POST      | `api/v1/mobile/devices` (tenant)                  | Sim   | N/A           | Sim            | ok                    |
| DELETE    | `api/v1/mobile/devices/{installationId}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD | `api/v1/mobile/notifications` (tenant)            | Sim   | N/A           | Sim            | ok                    |
| POST      | `api/v1/mobile/notifications/{id}/read` (tenant)  | Sim   | N/A           | Sim            | ok                    |

### modules

| Método   | Path                        | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | --------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/modules` (tenant) | Sim   | N/A           | Sim            | ok                    |

### municipios

| Método   | Path                                                     | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | -------------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/municipios/{ibge_codigo}/dados-sidra` (tenant) | Sim   | N/A           | Sim            | ok                    |

### negociacoes

| Método   | Path                                        | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/negociacoes` (tenant)             | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/negociacoes` (tenant)             | Sim   | Pro           | Sim            | ok                    |
| GET\|HEAD | `api/v1/negociacoes/{id}` (tenant)        | Sim   | Pro           | Sim            | ok                    |
| PUT       | `api/v1/negociacoes/{id}` (tenant)        | Sim   | Pro           | Sim            | ok                    |
| POST      | `api/v1/negociacoes/{id}/events` (tenant) | Sim   | Pro           | Sim            | ok                    |

### plans

| Método   | Path                                    | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | --------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/plans` (sigapp.com.br)        | Não  | N/A           | Não           | ok                    |
| GET\|HEAD | `api/v1/plans/{slug}` (sigapp.com.br) | Não  | N/A           | Não           | ok                    |

### premissas-viabilidade

| Método    | Path                                                              | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | ----------------------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/premissas-viabilidade` (tenant)                         | Sim   | Básico       | Sim            | ok                    |
| POST       | `api/v1/premissas-viabilidade` (tenant)                         | Sim   | Básico       | Sim            | ok                    |
| DELETE     | `api/v1/premissas-viabilidade/{premissas_viabilidade}` (tenant) | Sim   | Básico       | Sim            | ok                    |
| GET\|HEAD  | `api/v1/premissas-viabilidade/{premissas_viabilidade}` (tenant) | Sim   | Básico       | Sim            | ok                    |
| PUT\|PATCH | `api/v1/premissas-viabilidade/{premissas_viabilidade}` (tenant) | Sim   | Básico       | Sim            | ok                    |

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

### proprietarios

| Método    | Path                                             | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | ------------------------------------------------ | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/proprietarios` (tenant)                | Sim   | N/A           | Sim            | ok                    |
| POST       | `api/v1/proprietarios` (tenant)                | Sim   | N/A           | Sim            | ok                    |
| DELETE     | `api/v1/proprietarios/{proprietario}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/proprietarios/{proprietario}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/proprietarios/{proprietario}` (tenant) | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/proprietarios/select` (tenant)         | Sim   | N/A           | Sim            | ok                    |

### regionais

| Método    | Path                                     | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | ---------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/regionais` (tenant)            | Sim   | Broker        | Sim            | ok                    |
| POST       | `api/v1/regionais` (tenant)            | Sim   | Broker        | Sim            | ok                    |
| DELETE     | `api/v1/regionais/{regionai}` (tenant) | Sim   | Broker        | Sim            | ok                    |
| GET\|HEAD  | `api/v1/regionais/{regionai}` (tenant) | Sim   | Broker        | Sim            | ok                    |
| PUT\|PATCH | `api/v1/regionais/{regionai}` (tenant) | Sim   | Broker        | Sim            | ok                    |
| GET\|HEAD  | `api/v1/regionais/select` (tenant)     | Sim   | Broker        | Sim            | ok                    |

### signup

| Método   | Path                                                 | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados                                                 |
| --------- | ---------------------------------------------------- | ----- | ------------- | -------------- | --------------------------------------------------------------------- |
| POST      | `api/v1/signup` (sigapp.com.br)                    | Não  | N/A           | Não           | MÉDIO: trial repetível; sem ledger por identidade/meio de pagamento |
| GET\|HEAD | `api/v1/signup/{sessionId}/status` (sigapp.com.br) | Não  | N/A           | Não           | ok: session Stripe é segredo de alta entropia e vínculo é validado |

### start

| Método   | Path                      | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/start` (tenant) | Sim   | N/A           | Sim            | ok                    |

### tenant

| Método   | Path                       | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | -------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/tenant` (tenant) | Sim   | N/A           | Sim            | ok                    |

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

### tenant-status

| Método   | Path                                     | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ---------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/tenant-status` (sigapp.com.br) | Sim   | N/A           | Não           | ok                    |

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

### terreno-produtos

| Método    | Path                                                        | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| ---------- | ----------------------------------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD  | `api/v1/terreno-produtos` (tenant)                        | Sim   | N/A           | Sim            | ok                    |
| POST       | `api/v1/terreno-produtos` (tenant)                        | Sim   | N/A           | Sim            | ok                    |
| DELETE     | `api/v1/terreno-produtos/{terreno_produto}` (tenant)      | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/terreno-produtos/{terreno_produto}` (tenant)      | Sim   | N/A           | Sim            | ok                    |
| PUT\|PATCH | `api/v1/terreno-produtos/{terreno_produto}` (tenant)      | Sim   | N/A           | Sim            | ok                    |
| GET\|HEAD  | `api/v1/terreno-produtos/by-terreno/{terrenoId}` (tenant) | Sim   | N/A           | Sim            | ok                    |

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

### users

| Método   | Path                                 | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ------------------------------------ | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `api/v1/users/for-select` (tenant) | Sim   | N/A           | Sim            | ok                    |

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

### telescope

| Método   | Path                                                           | Auth?      | Plano mínimo | Tenant-scoped? | Problemas encontrados                |
| --------- | -------------------------------------------------------------- | ---------- | ------------- | -------------- | ------------------------------------ |
| GET\|HEAD | `telescope/{view?}`                                          | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/batches`                            | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/batches/{telescopeEntryId}`         | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/cache`                              | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/cache/{telescopeEntryId}`           | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/client-requests`                    | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/client-requests/{telescopeEntryId}` | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/commands`                           | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/commands/{telescopeEntryId}`        | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/dumps`                              | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| DELETE    | `telescope/telescope-api/entries`                            | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/events`                             | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/events/{telescopeEntryId}`          | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/exceptions`                         | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/exceptions/{telescopeEntryId}`      | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| PUT       | `telescope/telescope-api/exceptions/{telescopeEntryId}`      | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/gates`                              | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/gates/{telescopeEntryId}`           | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/jobs`                               | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/jobs/{telescopeEntryId}`            | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/logs`                               | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/logs/{telescopeEntryId}`            | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/mail`                               | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/mail/{telescopeEntryId}`            | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/mail/{telescopeEntryId}/download`   | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/mail/{telescopeEntryId}/preview`    | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/models`                             | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/models/{telescopeEntryId}`          | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/monitored-tags`                     | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/monitored-tags`                     | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/monitored-tags/delete`              | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/notifications`                      | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/notifications/{telescopeEntryId}`   | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/queries`                            | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/queries/{telescopeEntryId}`         | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/redis`                              | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/redis/{telescopeEntryId}`           | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/requests`                           | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/requests/{telescopeEntryId}`        | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/schedule`                           | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/schedule/{telescopeEntryId}`        | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/toggle-recording`                   | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| POST      | `telescope/telescope-api/views`                              | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |
| GET\|HEAD | `telescope/telescope-api/views/{telescopeEntryId}`           | Sim (Gate) | N/A           | Não           | ok: protegido pelo gate do Telescope |

### tenancy

| Método   | Path                                | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ----------------------------------- | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `tenancy/assets/{path?}` (tenant) | Não  | N/A           | Sim            | ok                    |

### up

| Método   | Path   | Auth? | Plano mínimo | Tenant-scoped? | Problemas encontrados |
| --------- | ------ | ----- | ------------- | -------------- | --------------------- |
| GET\|HEAD | `up` | Não  | N/A           | Não           | ok                    |
