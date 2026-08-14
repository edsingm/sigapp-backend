# Multi-Tenancy

> **Quando ler:** central × tenant, identificação de host/`X-Tenant`, schemas, cache, ciclo de vida do tenant.
> **Hub:** [`AGENTS.md`](../../AGENTS.md)

## Conceito central

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

- **Identificação do tenant**: subdomínio em hosts tenant; clientes mobile/API também podem enviar `X-Tenant` quando acessam um host central exato configurado (middleware `App\Http\Middleware\InitializeTenancyFlexible`). O header aceita somente slugs alfanuméricos com hífen e nunca substitui o tenant de um host tenant. O middleware global `EnforceHostAccess` libera somente hosts centrais exatos e slugs de tenants cadastrados; navegação segura (`GET`/`HEAD`, fora de `/api`) em subdomínio desconhecido de `APP_DOMAIN` redireciona temporariamente para `FRONTEND_URL`, enquanto APIs e métodos mutáveis retornam `404 TENANT_NOT_FOUND`.
- Subdomínios centrais (`app`, `admin`, `www`) e nomes de infraestrutura configurados em `TENANCY_RESERVED_SUBDOMAINS` são indisponíveis para signup. Serviços DNS como `smtp` ficam reservados, mas não são tratados como aplicações HTTP. Ao adicionar um host web central, inclua o domínio completo em `CENTRAL_DOMAINS`; ao adicionar um nome DNS que nunca poderá ser tenant, inclua seu label em `TENANCY_RESERVED_SUBDOMAINS`.
- Os middlewares `central.context` (`EnsureCentralContext`) e `tenant.context` (`EnsureTenantContext`) garantem que a rota roda no contexto certo — **toda rota nova deve declarar um deles**.
- `auth.central` / `auth.tenant` garantem que o usuário autenticado pertence ao contexto (guard Sanctum é compartilhado).
- Nunca referencie um model `Central` dentro de código tenant (e vice-versa) sem necessidade explícita — quando precisar (ex.: `Tenant`, `Plan`), acesse via serviço/`tenancy()`.
- O manager de banco é o customizado `App\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaPublicManager` (schemas, não bancos separados). O identificador vem de `Tenant::makeTenantDatabaseIdentifier($slug)` (ver `TenancyServiceProvider::register()`).
- O cache é isolado pela tag-base automática `tenant{id}` do `CacheTenancyBootstrapper` do Stancl v3.10; chaves/tags de módulos e locks de preenchimento passam por `TenantCacheService`. A invalidação seletiva usa o store bruto somente dentro desse serviço, pois `Cache::tags(...)->flush()` no contexto tenant também resetaria a tag-base e derrubaria todo o cache do tenant. O cache RBAC do Spatie usa chave própria por tenant e o `PermissionRegistrar` é recriado nas transições de tenancy. Redis e storage local continuam contextualizados por tenant (ver `config/tenancy.php`). Documentos/versões, relatórios de IA, anexos mobile, execuções do report builder e exports contam `storage_gb` por objeto físico único `(disk, path)` através do repository de métricas. Artefatos gerados são medidos sob lock antes da conclusão e apagados se excederem a franquia; preserve esse fluxo ao adicionar novas superfícies de arquivo.
- Ciclo de vida do tenant: signup público (`SignupController` → `TenantSignupService` → `CreateFullTenantJob`), limpeza de pendentes (`tenants:cleanup-pending`), ativação/suspensão via admin central (`TenantStatus`). `Tenant::cancel()` hoje só muda o status para `cancelled` — schema e S3 permanecem. O drop de schema ocorre apenas quando o model `Tenant` é *deleted* (`DeleteDatabase`); `DeleteTenantStorage` está comentado. Offboarding D90 (`cancelled_at` → wipe em 90 dias, flag `PRIVACY_AUTO_WIPE_ENABLED`) é SIG-26 PR7 — ver [`.agents/docs/privacidade-lgpd.md`](./privacidade-lgpd.md).
- Cada tenant já recebe `encryption_key` em `CreateFullTenantJob`. A chave **não** é lida hoje; `billing_tax_id` usa cast `encrypted` com `APP_KEY`. Cifra de PII do schema tenant com essa chave é SIG-26 PR9.
- Scripts auxiliares de operação em `scripts/pgsql/` (criação de schemas, descoberta de tenants, reset de sequences, validação de contagens).
