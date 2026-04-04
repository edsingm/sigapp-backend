# Planos e Entitlements — Migração para banco de dados

## Contexto

Anteriormente, os planos e seus recursos eram definidos estaticamente em `config/plans.php`. Qualquer alteração de features ou limites exigia mudança de código e novo deploy.

Este documento descreve a nova arquitetura, que move toda a configuração de planos para o banco de dados, tornando-a editável via API.

---

## O que foi removido

| Arquivo | Motivo |
|---|---|
| `config/plans.php` | Substituído por dados no banco (`entitlements` + `plan_entitlements`) |
| `PlanMatrixService::assertConfiguredSlugs()` | Verificação de slugs contra o config não se aplica mais |
| `AppServiceProvider` — boot validation de slugs | Idem |

---

## Arquitetura nova

```
plans               → metadados do plano (nome, preço, stripe_price_id, etc.)
entitlements        → catálogo de recursos disponíveis no sistema
plan_entitlements   → valor de cada entitlement para cada plano (pivot)
```

### Tipos de entitlement (`EntitlementType`)

| Valor | Descrição | Tipo do valor |
|---|---|---|
| `feature` | Funcionalidade booleana | `bool` |
| `limit` | Limite numérico | `int` (-1 = ilimitado) |

### Formato das chaves (`key`)

Usa dot-notation para espelhar a estrutura hierárquica original:

```
home
prospection
dashboard.enabled
dashboard.vgv
viabilities.enabled
viabilities.dre
exports.pdf
users          ← limit
terrenos       ← limit
```

O `PlanMatrixService` reconstrói os arrays `features` e `limits` a partir dessas chaves usando `data_set()`, mantendo a mesma estrutura aninhada que o `CheckFeature` middleware e o `EnforcePlanLimits` middleware já esperam.

---

## Camadas implementadas

### Enums
- `app/Enums/Common/EntitlementType.php` — `FEATURE` | `LIMIT`

### Models
- `app/Models/Central/Entitlement.php` — model central com `CentralConnection`
- `app/Models/Central/Plan.php` — adicionado `entitlements(): BelongsToMany`; `price` agora é `double` (BRL direto, ex: `97.00`); accessor `formatted_price` formata sem divisão por 100
- `app/Models/Central/TenantEntitlement.php` — model central para entitlements extras por tenant
- `app/Models/Central/Tenant.php` — adicionado `extraEntitlements(): HasMany` e `extra_monthly_cost` accessor

### Migrations
- `2026_04_03_000001_create_entitlements_table.php`
- `2026_04_03_000002_create_plan_entitlements_table.php`
- `2026_04_03_000003_create_tenant_entitlements_table.php`
- `2026_04_04_000010_change_price_to_double_in_plans_table.php` — altera `plans.price` de `integer` para `double(8,2)`, representando o valor em BRL diretamente (ex: `97.00` = R$ 97,00)

### Repositories
- `app/Repositories/Contracts/EntitlementRepositoryInterface.php`
- `app/Repositories/Contracts/PlanRepositoryInterface.php`
- `app/Repositories/EntitlementRepository.php`
- `app/Repositories/PlanRepository.php`

### Services
- `app/Services/EntitlementService.php`
- `app/Services/PlanService.php`
- `app/Services/PlanMatrixService.php` — refatorado para ler do DB via `PlanRepositoryInterface`; adicionados métodos tenant-aware (`resolveForTenant`, `hasFeatureForTenant`, `getLimitForTenant`, `isUnlimitedLimitForTenant`)
- `app/Services/TenantPlanService.php` — atribuição/upgrade/downgrade de plano e gestão de entitlements extras por tenant

### HTTP
- `app/Http/Requests/Admin/StoreEntitlementRequest.php`
- `app/Http/Requests/Admin/UpdateEntitlementRequest.php`
- `app/Http/Requests/Admin/StorePlanRequest.php`
- `app/Http/Requests/Admin/UpdatePlanRequest.php`
- `app/Http/Requests/Admin/SyncPlanEntitlementsRequest.php`
- `app/Http/Requests/Admin/AssignTenantPlanRequest.php`
- `app/Http/Requests/Admin/AddTenantEntitlementRequest.php`
- `app/Http/Requests/Admin/UpdateTenantEntitlementRequest.php`
- `app/Http/Resources/EntitlementResource.php`
- `app/Http/Resources/PlanResource.php` — adicionado `entitlements` (lazy-loaded)
- `app/Http/Resources/TenantEntitlementResource.php`
- `app/Http/Controllers/Api/V1/Admin/EntitlementController.php`
- `app/Http/Controllers/Api/V1/Admin/PlanAdminController.php`
- `app/Http/Controllers/Api/V1/Admin/TenantPlanController.php`

### Seeders
- `database/seeders/EntitlementSeeder.php` — popula entitlements e sincroniza valores iniciais por plano
- `database/seeders/DatabaseSeeder.php` — `EntitlementSeeder` chamado após `PlanSeeder`

---

## Rotas

Todas protegidas por `auth:sanctum` + `user.admin`.

### Entitlements (catálogo de recursos)

| Método | Rota | Ação |
|---|---|---|
| `GET` | `/api/v1/admin/entitlements` | Listar todos |
| `POST` | `/api/v1/admin/entitlements` | Criar novo |
| `GET` | `/api/v1/admin/entitlements/{id}` | Detalhar |
| `PUT/PATCH` | `/api/v1/admin/entitlements/{id}` | Atualizar |
| `DELETE` | `/api/v1/admin/entitlements/{id}` | Remover |

### Plans (CRUD admin)

| Método | Rota | Ação |
|---|---|---|
| `GET` | `/api/v1/admin/plans` | Listar todos |
| `POST` | `/api/v1/admin/plans` | Criar plano |
| `GET` | `/api/v1/admin/plans/{id}` | Detalhar (inclui entitlements) |
| `PUT/PATCH` | `/api/v1/admin/plans/{id}` | Atualizar metadados |
| `DELETE` | `/api/v1/admin/plans/{id}` | Remover (bloqueado se houver tenants) |
| `PUT` | `/api/v1/admin/plans/{id}/entitlements` | Sincronizar entitlements do plano |

#### Payload de `PUT /plans/{id}/entitlements`

```json
{
  "entitlements": [
    { "entitlement_id": 1, "value": true },
    { "entitlement_id": 25, "value": 10 }
  ]
}
```

> Esta operação é destrutiva para o plano: substitui **todos** os entitlements atuais pelo array enviado.

---

## Cache

A matriz `features`/`limits` de cada plano é cacheada em Redis sob a tag `plan_matrix_{id}` por 1 hora. O cache é invalidado automaticamente sempre que:

- `PlanRepository::syncEntitlements()` é chamado
- `PlanRepository::delete()` é chamado
- O método `invalidateMatrixCache(int $planId)` é chamado manualmente

---

## Setup inicial

Após o primeiro deploy com as novas migrations:

```bash
php artisan migrate
php artisan db:seed --class=EntitlementSeeder
```

O seeder popula o catálogo completo de entitlements e sincroniza os valores de cada plano com os valores que estavam no antigo `config/plans.php`.

---

## Compatibilidade

Os middlewares `check.feature` e `enforce.limits` foram atualizados para usar métodos **tenant-aware** do `PlanMatrixService`. Ambos resolvem a matriz efetiva considerando os entitlements extras do tenant sobre a base do plano:

- `CheckFeature` → usa `planMatrix->hasFeatureForTenant($tenant, $feature)`
- `EnforcePlanLimits` → usa `planMatrix->getLimitForTenant($tenant, $key)` e `planMatrix->isUnlimitedLimitForTenant($tenant, $key)`

Se o tenant não possui entitlements extras, o comportamento é idêntico ao anterior (apenas o plano base).

---

## Atribuição de plano ao tenant

### Tabela `tenant_entitlements`

Armazena entitlements extras contratados individualmente por um tenant, com custo adicional:

| Coluna | Tipo | Descrição |
|---|---|---|
| `tenant_id` | `string` | FK → `tenants.id` |
| `entitlement_id` | `int` | FK → `entitlements.id` |
| `value` | `json` | Valor do entitlement (bool/int) |
| `price` | `int` | Custo mensal adicional em centavos |

Constraint `UNIQUE(tenant_id, entitlement_id)` garante que cada entitlement só pode ser adicionado uma vez por tenant.

### Resolução efetiva da matriz

A matriz efetiva de um tenant é calculada em `PlanMatrixService::resolveForTenant(Tenant)`:

1. Carrega a matriz base do plano via `PlanRepository::getMatrix()` (cacheada em Redis)
2. Carrega os entitlements extras do tenant com eager-loading de `entitlement`
3. Mescla os extras sobre a matriz base — extras sobrescrevem o valor do plano base

### Custo total

O custo mensal total de um tenant é:

```
custo_total = plano.price + sum(tenant_entitlements.price)
```

O accessor `$tenant->extra_monthly_cost` retorna a soma dos preços extras em centavos.

---

## Rotas de gestão de plano por tenant

Todas protegidas por `auth:sanctum` + `user.admin`.

### Atribuição de plano

| Método | Rota | Ação |
|---|---|---|
| `POST` | `/api/v1/admin/tenants/{id}/plan` | Atribuir plano (substitui o atual) |
| `PUT` | `/api/v1/admin/tenants/{id}/plan/upgrade` | Upgrade (sort_order maior) |
| `PUT` | `/api/v1/admin/tenants/{id}/plan/downgrade` | Downgrade (sort_order menor) |

#### Payload (assign / upgrade / downgrade)

```json
{ "plan_id": 3 }
```

#### Resposta

```json
{
  "success": true,
  "data": { /* PlanResource do novo plano */ },
  "message": "..."
}
```

#### Erros

| Código | Status | Motivo |
|---|---|---|
| `INVALID_PLAN` | 422 | Plano inexistente ou inativo |
| `UPGRADE_FAILED` | 422 | sort_order do novo plano não é superior ao atual |
| `DOWNGRADE_FAILED` | 422 | sort_order do novo plano não é inferior ao atual |

---

### Entitlements extras por tenant

| Método | Rota | Ação |
|---|---|---|
| `GET` | `/api/v1/admin/tenants/{id}/entitlements` | Listar extras do tenant |
| `POST` | `/api/v1/admin/tenants/{id}/entitlements` | Adicionar extra |
| `PUT` | `/api/v1/admin/tenants/{id}/entitlements/{entitlementId}` | Atualizar valor/preço |
| `DELETE` | `/api/v1/admin/tenants/{id}/entitlements/{entitlementId}` | Remover extra |

#### Payload POST (adicionar)

```json
{
  "entitlement_id": 25,
  "value": 20,
  "price": 4990
}
```

- `value`: `true`/`false` para features, inteiro para limites
- `price`: custo mensal adicional em **centavos** (ex: `4990` = R$ 49,90)

#### Payload PUT (atualizar)

```json
{
  "value": 50,
  "price": 9900
}
```

#### Resposta (item)

```json
{
  "id": 1,
  "entitlement_id": 25,
  "entitlement": { /* EntitlementResource */ },
  "value": 20,
  "price": 4990,
  "price_formatted": "R$ 49,90",
  "created_at": "2026-04-03T00:00:00+00:00",
  "updated_at": "2026-04-03T00:00:00+00:00"
}
```

---

## Catálogo de entitlements do sistema

### Features

| Key | Label |
|---|---|
| `home` | Home |
| `prospection` | Prospecção |
| `committee` | Comitê de Revisão |
| `negotiation` | Negociações |
| `legalizations` | Legalizações |
| `projects_room` | Sala de Projetos |
| `product_settings` | Configuração de Produtos |
| `regionals` | Regionais |
| `territorial_base` | Base Territorial |
| `ai` | Assistente de IA |
| `dashboard.enabled` | Dashboard |
| `dashboard.overview` | Dashboard — Visão Geral |
| `dashboard.units_closed` | Dashboard — Units Fechadas |
| `dashboard.vgv` | Dashboard — VGV |
| `dashboard.funnel` | Dashboard — Funil |
| `viabilities.enabled` | Viabilidades |
| `viabilities.summary` | Viabilidades — Resumo |
| `viabilities.dre` | Viabilidades — DRE |
| `viabilities.cash_flow` | Viabilidades — Fluxo de Caixa |
| `viabilities.charts` | Viabilidades — Gráficos |
| `viabilities.premises` | Viabilidades — Premissas |
| `viabilities.kpis` | Viabilidades — KPIs |
| `exports.excel` | Exportação Excel |
| `exports.pdf` | Exportação PDF |

### Limits (use `-1` para ilimitado)

| Key | Label |
|---|---|
| `users` | Limite de usuários |
| `terrenos` | Limite de terrenos |
| `products` | Limite de produtos |
| `storage_gb` | Armazenamento (GB) |

---

## Matriz inicial dos planos

| Feature / Limit | broker | basico | master | pro |
|---|:---:|:---:|:---:|:---:|
| `home` | ✅ | ✅ | ✅ | ✅ |
| `prospection` | ✅ | ✅ | ✅ | ✅ |
| `dashboard.enabled` | ❌ | ✅ | ✅ | ✅ |
| `dashboard.overview` | ❌ | ✅ | ✅ | ✅ |
| `dashboard.units_closed` | ❌ | ❌ | ✅ | ✅ |
| `dashboard.vgv` | ❌ | ❌ | ✅ | ✅ |
| `dashboard.funnel` | ❌ | ❌ | ✅ | ✅ |
| `viabilities.enabled` | ❌ | ✅ | ✅ | ✅ |
| `viabilities.summary` | ❌ | ✅ | ✅ | ✅ |
| `viabilities.dre` | ❌ | ✅ | ✅ | ✅ |
| `viabilities.cash_flow` | ❌ | ❌ | ✅ | ✅ |
| `viabilities.charts` | ❌ | ❌ | ❌ | ✅ |
| `viabilities.premises` | ❌ | ❌ | ❌ | ✅ |
| `viabilities.kpis` | ❌ | ❌ | ❌ | ✅ |
| `committee` | ❌ | ❌ | ❌ | ✅ |
| `ai` | ❌ | ❌ | ✅ | ✅ |
| `negotiation` | ❌ | ❌ | ❌ | ✅ |
| `legalizations` | ❌ | ❌ | ❌ | ✅ |
| `projects_room` | ❌ | ❌ | ❌ | ✅ |
| `product_settings` | ✅ | ✅ | ✅ | ✅ |
| `regionals` | ✅ | ✅ | ✅ | ✅ |
| `territorial_base` | ✅ | ✅ | ✅ | ✅ |
| `exports.excel` | ✅ | ✅ | ✅ | ✅ |
| `exports.pdf` | ❌ | ✅ | ✅ | ✅ |
| `users` (limit) | 1 | 3 | 10 | ∞ |
| `terrenos` (limit) | 50 | 100 | 200 | ∞ |
| `products` (limit) | 1 | 2 | 3 | ∞ |
| `storage_gb` (limit) | 0 | 1 | 3 | 5 |
