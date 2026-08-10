# Autenticação, RBAC e rotas da API

> **Quando ler:** login broker/tenant/admin MFA, Spatie roles/permissions, middlewares, rate limits, health, Scramble.
> **Hub:** [`AGENTS.md`](../../AGENTS.md)
> **Áreas sensíveis:** transfer ticket, MFA admin, webhooks sem throttle.

## Autenticação, autorização e RBAC

#### Fluxos de autenticação (Sanctum, tokens Bearer)

1. **Login central (broker)**: `POST /api/v1/auth/login` (domínio central) → `CentralAuthController` + `CentralLoginBrokerService` resolvem os tenants do e-mail (via `TenantUserDirectory`) → `POST /auth/select-tenant` emite um **transfer ticket** → o frontend chama `POST /api/v1/auth/exchange-ticket` no **subdomínio do tenant** e recebe o token Sanctum do tenant. A sessão temporária do broker é vinculada ao IP que iniciou o login; a seleção feita por outro IP falha com a mesma resposta genérica de sessão inválida. A conclusão da sessão e a criação do ticket ocorrem na mesma transaction com claim condicional, garantindo um único ticket mesmo sob seleções concorrentes.
2. **Login direto no tenant**: `POST /api/v1/auth/login` no subdomínio (`TenantAuthController`/`TenantLoginService`).
3. **Login admin da plataforma**: `POST /api/v1/admin/login` (`AdminController`) valida apenas a senha e devolve um desafio; `POST /api/v1/admin/login/verify` valida TOTP ou código de recuperação e só então emite o token Sanctum com abilities `admin` + `admin:mfa`. O primeiro acesso faz o cadastro TOTP em duas etapas. O middleware `central.admin` (`EnsureUserIsAdmin`) exige ambas as abilities e `admin_mfa_confirmed_at`; tokens administrativos não renovam por `/auth/refresh` e a sessão MFA expira em 12 horas.
   - A chave TOTP fica criptografada no model `User`; desafios armazenam somente hash do token e segredo pendente criptografado, têm TTL, limite de cinco tentativas, proteção contra replay por timestep e invalidação por `admin_mfa_version`. A rotação exige senha + fator atual, cria um segundo desafio, revoga tokens e substitui os códigos de recuperação. Use `GET /api/v1/admin/mfa` para o status, `POST /api/v1/admin/mfa/rotate` + `/verify` para rotação e `POST /api/v1/admin/mfa/recovery-codes` para regeneração.
4. Reset de senha do tenant funciona tanto pelo domínio central quanto pelo do tenant (`TenantPasswordResetController`; URLs geradas por `App\Support\TenantAppUrl`).
- Novos tenants nascem com o perfil fiscal obrigatório pendente. Login direto, exchange ticket e `GET /api/v1/start` expõem somente o resumo seguro `tenant.billing_profile`; o ADMIN consulta/atualiza os dados completos em `GET|PUT /api/v1/tenant/billing-profile`. O middleware `tenant.billing-profile.complete` bloqueia os módulos de negócio com `428 TENANT_BILLING_PROFILE_INCOMPLETE` até a conclusão, sem bloquear autenticação, bootstrap ou regularização de cobrança. O perfil pertence ao model central `Tenant`, CPF/CNPJ usa cast criptografado e tenants anteriores à migration de rollout permanecem explicitamente dispensados (`billing_profile_required=false`). Não reutilize o onboarding opcional por usuário para essa obrigação.
- CNPJ deve aceitar os formatos numérico legado e alfanumérico da Receita Federal: 14 posições, letras maiúsculas `A-Z` e números nas 12 primeiras posições, dois dígitos verificadores numéricos e cálculo módulo 11 com valor ASCII menos 48 para cada caractere. Nunca converta um CNPJ alfanumérico somente com `digits()`; normalize separadores preservando letras e mantenha a formatação `XX.XXX.XXX/XXXX-XX`.
- Sessões do broker expiram e são limpas por `auth:cleanup-central-login-broker` (a cada 5 min). Desafios MFA expirados/consumidos são limpos por `admin:mfa-cleanup` (diário); o reset operacional, que revoga tokens e exige novo cadastro, é `admin:mfa-reset {email} --operator=... --reason=...`.

#### RBAC

- **Spatie Permission** (`teams => false`) nos usuários do tenant. Roles canônicas em `App\Enums\Common\RolesEnum`: `ADMIN`, `DIRECTOR`, `MANAGER`, `SUPERVISOR`, `ANALYST`, `USER`. **Sempre use o enum** — nunca strings soltas de role.
- Permissões por módulo/submódulo: `App\Enums\Common\ModulesEnum` (`admin`, `configurations`, `prospection`, `brokers`, `data`, `dashboard`, `committee`, `legal`, `negotiation`, `projects`, `reports`, `viability`, `ai`) + `SubmodulesEnum`; nomes resolvidos por `Services\Acl\PermissionNameResolver`.
- O que o tenant pode usar é a interseção de: **plano/entitlements** (middleware `check.feature`, `enforce.limits`, `subscription.active`) + **RBAC do usuário** (middleware `permission.gate`, FormRequest `authorize()`).
- Templates de permissão por plano: `database/rbacTemplates/` + `PlanRolePermissionTemplate` + comandos `rbac:apply-templates` e `tenants:sync-acl`.
- No módulo `configurations`, os templates padrão concedem `manager` ao ADMIN, `viewer` a DIRECTOR/MANAGER/SUPERVISOR e nenhum acesso a ANALYST/USER; Personalização é uma área comum do frontend e Faturamento permanece exclusivo do ADMIN.
- Autorização acontece **antes** do Service (rota/middleware/FormRequest). **Services nunca tratam autorização.**
- Helper `user()` (em `app/Support/helpers.php`) retorna `UserContext` — use `user()->getType()` (`UserType::SIGAPP|TENANT`), não checagens manuais de classe.

#### Aliases de middleware (bootstrap/app.php)

`force.json`, `tenant.logs`, `api.logger`, `central.context`, `tenant.context`, `auth.central`, `auth.tenant`, `enforce.limits`, `subscription.active`, `tenant.billing-profile.complete`, `central.admin`, `tenant.admin`, `user.admin`, `permission.gate`, `check.feature`, `ai.rate_limit`, `ai.budget` — além do grupo `tenant` (`InitializeTenancyFlexible`) e dos globais `SecurityHeaders` e `EnforceHostAccess`. Login MFA usa o limiter nomeado `admin-mfa` (por IP e desafio) além do rate limit por conta no service.

## Rotas da API

- API **versionada** em `/api/v1/`. Central em `routes/api.php`; o agregador tenant fica em `routes/tenant.php` e carrega, na ordem declarada, os módulos em `routes/tenant/`.
- Middlewares comuns, prefixo versionado e proteção de assinatura permanecem no agregador tenant. Arquivos em `routes/tenant/` declaram somente suas rotas de domínio; todo arquivo modular deve ser carregado exatamente uma vez pelo agregador. O `TenancyServiceProvider` não registra essas rotas novamente quando o cache de rotas está ativo.
- **Rate limiting é obrigatório e nomeado** — os limiters são definidos no topo de `routes/api.php` (`api-public`, `api-auth`, `central-login`, `admin-login`, `admin-mfa`, `transfer-ticket`, `password-reset-*`, `signup-status`, `consent-log`, `viabilidade-approval`, ...). Rota nova entra num grupo com throttle existente ou ganha limiter próprio com resposta via `ApiResponseService::tooManyRequests()`.
- A captura pública de demonstração usa `POST /api/v1/demo-request`, no contexto central, com o limiter dedicado `demo-request`. O lead é persistido em `demo_requests` e a notificação interna é enviada de forma assíncrona ao `CENTRAL_ADMIN_EMAIL`; o IP bruto não é armazenado.
- Rotas centrais ficam dentro do loop de `central_domains` em `routes/api.php`. O primeiro domínio preserva os nomes canônicos (`admin.*`); os domínios alternativos recebem o prefixo interno `central-domain-{index}.` para que `route:cache` não encontre nomes duplicados. Siga esse padrão ao criar rotas centrais nomeadas.
- Rotas tenant novas: declare `tenant.context` + `auth:sanctum` + `auth.tenant` + `throttle:api-auth`, e o gate de módulo/assinatura adequado (`check.feature:...`, `subscription.active`, `tenant.admin`, `permission.gate`).
- `GET /api/v1/modules` mantém o contrato legado. `GET /api/v1/start` adiciona `access.features`, `access.limits` e `access.modules`; essa é a fonte oficial da matriz efetiva (plano + override), combinando módulo ativo, plano e RBAC e expondo `reasons` (`module`, `plan`, `rbac`) quando indisponível.
- Use Route Model Binding e kebab-case plural nos paths. Webhook Stripe (`POST /webhook/stripe`) fica **sem** throttle/CSRF — não mexa nisso sem entender o motivo.
- Health checks: `/up` (framework), `GET /api/health` (mínimo/legado usado pelo Docker), `GET /api/v1/health` (público versionado, mínimo), `GET /api/v1/health/details` (admin central autenticado, inclui disponibilidade/versão do `pgvector`) e `GET /api/health` no tenant (autenticado, inclui dados do tenant).
- A documentação Scramble é gerada das rotas/FormRequests/Resources — mantenha tipos e PHPDocs corretos para a doc sair certa em `/docs/api`.
