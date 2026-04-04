# Módulos — Arquitetura e Implementação

## Visão Geral

O sistema de módulos define a estrutura de navegação da aplicação. Cada módulo representa uma área funcional do produto (ex: Prospecção, Viabilidade, Dashboard). Módulos são agrupados em **setores** (seções visuais no sidebar), e podem conter **submódulos** (recursos internos com permissões próprias).

---

## Arquitetura

```
ModulesEnum          → define todos os módulos do sistema (enum PHP)
SectorsEnum          → define os setores (agrupamentos) do sistema
SubmodulesEnum       → define os submódulos (recursos dentro de um módulo)
Modules (model)      → persiste módulos no banco central (ativo, ordem, ícone)
ModulesService       → consulta o banco e agrupa por setor
ModulesController    → expõe via API pública
ModulesResource      → formata cada módulo para o frontend
```

---

## Enums

### `ModulesEnum`

Localização: `app/Enums/Common/ModulesEnum.php`

Enumera todos os módulos do sistema. Cada case representa uma área funcional.

| Case | Valor | Setor | Ordem |
|---|---|---|---|
| `DASHBOARD` | `dashboard` | PRINCIPAL | 10 |
| `PROSPECTION` | `prospection` | OPERATION | 20 |
| `BROKERS` | `brokers` | OPERATION | 30 |
| `VIABILITY` | `viability` | OPERATION | 40 |
| `COMMITTEE` | `committee` | OPERATION | 50 |
| `NEGOTIATION` | `negotiation` | OPERATION | 60 |
| `LEGAL` | `legal` | OPERATION | 70 |
| `PROJECTS` | `projects` | OPERATION | 80 |
| `AI` | `ai` | OPERATION | 130 |
| `CONFIGURATIONS` | `configurations` | CONFIGURATION | 90 |
| `DATA` | `data` | CONFIGURATION | 100 |
| `REPORTS` | `reports` | INTELLIGENCE | 110 |
| `ADMIN` | `admin` | ADMINISTRATION | 120 |

**Métodos disponíveis:**

```php
ModulesEnum::PROSPECTION->label()       // 'Prospecção' (via i18n)
ModulesEnum::PROSPECTION->order()       // 20
ModulesEnum::PROSPECTION->sector()      // SectorsEnum::OPERATION
ModulesEnum::PROSPECTION->submodules()  // [SubmodulesEnum::TERRAINS, SubmodulesEnum::MAPS]
ModulesEnum::PROSPECTION->hasSubmodules() // true
ModulesEnum::PROSPECTION->models()      // [Terreno::class => 'terrains', ...]

// Mapeamento completo modelo → permissão base (static, memoizado)
ModulesEnum::modelMap()
// ['App\Models\Tenant\Terreno' => 'prospection.terrains', ...]
```

**Integração com ACL:**

O `ModelMap` é usado pelo `TenantPolicy` para mapear qualquer modelo Eloquent para a string de permissão correta (`modulo.recurso` ou `modulo`), sem necessidade de condicionais no Policy.

O `AclController::catalog()` usa `ModulesEnum::cases()` + `submodules()` para construir o catálogo completo de permissões do sistema.

### `SectorsEnum`

Localização: `app/Enums/Common/SectorsEnum.php`

Agrupa módulos em seções do sidebar.

| Case | Valor | Label | Ordem |
|---|---|---|---|
| `PRINCIPAL` | `principal` | Principal | 1 |
| `OPERATION` | `operation` | Operação | 2 |
| `CONFIGURATION` | `configuration` | Configurações | 3 |
| `INTELLIGENCE` | `intelligence` | Inteligência | 4 |
| `ADMINISTRATION` | `administration` | Administração | 5 |

### `SubmodulesEnum`

Localização: `app/Enums/Common/SubmodulesEnum.php`

Define submódulos (recursos com permissões próprias dentro de um módulo).

| Case | Valor | Módulo pai |
|---|---|---|
| `TERRAINS` | `terrains` | PROSPECTION |
| `MAPS` | `maps` | PROSPECTION |

---

## Banco de Dados

### Tabela `modules`

Migration: `2026_03_14_181934_create_modules_table.php`

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | `bigint` | PK auto-increment |
| `slug` | `string unique` | Valor do `ModulesEnum` (ex: `prospection`) |
| `icon` | `string nullable` | Ícone para o frontend |
| `resources` | `json nullable` | Array de slugs de submódulos (ex: `["terrains","maps"]`) |
| `description` | `text nullable` | Descrição do módulo |
| `order` | `tinyint` | Ordem de exibição |
| `active` | `boolean` | Se o módulo está ativo no sistema |

### Model `Modules`

Localização: `app/Models/Central/Modules/Modules.php`

Usa `CentralConnection` (banco central). Atributos computados via `Attribute`:

```php
$module->name       // ModulesEnum::from($slug)->label() — via i18n
$module->sector     // ModulesEnum::from($slug)->sector() — SectorsEnum
$module->submodules // ModulesEnum::from($slug)->submodules() — array<SubmodulesEnum>
```

---

## Seeder

`database/seeders/ModulesSeeder.php`

Popula (upsert) todos os módulos baseado nos cases do `ModulesEnum`. Deve ser executado após migrations.

```bash
php artisan db:seed --class=ModulesSeeder
```

---

## Service

### `ModulesService::getAllModules()`

Localização: `app/Services/Modules/ModulesService.php`

```php
/**
 * Retorna todos os módulos ativos, agrupados por setor (ordenados).
 * @return array<string, Collection<Modules>>
 */
public function getAllModules(): array
```

1. Busca todos os módulos com `active = true`, ordenados por `order`
2. Agrupa por `$module->sector->value`
3. Ordena os setores por `SectorsEnum::from($value)->order()`
4. Retorna `array<sector_value, Collection<Modules>>`

---

## API

### Rota

```
GET /api/v1/modules
```

Rota pública (não requer autenticação). Disponível no contexto de tenant (`routes/tenant.php`).

### Response

```json
{
  "success": true,
  "data": [
    {
      "sector": {
        "slug": "principal",
        "label": "Principal",
        "order": 1
      },
      "modules": [
        {
          "slug": "dashboard",
          "name": "Dashboard",
          "icon": null,
          "description": null,
          "order": 10,
          "active": true,
          "submodules": []
        }
      ]
    },
    {
      "sector": {
        "slug": "operation",
        "label": "Operação",
        "order": 2
      },
      "modules": [
        {
          "slug": "prospection",
          "name": "Prospecção",
          "icon": null,
          "description": null,
          "order": 20,
          "active": true,
          "submodules": [
            { "slug": "terrains", "label": "Terrenos" },
            { "slug": "maps", "label": "Mapas" }
          ]
        }
      ]
    }
  ],
  "message": "..."
}
```

> Os setores são ordenados por `SectorsEnum::order()`. Dentro de cada setor, os módulos são ordenados por `modules.order` (DB).

### Campos omitidos intencionalmente

A resposta **não** expõe:
- `id` (PK interna)
- `created_at` / `updated_at`

---

## Resource

`app/Http/Resources/Tenant/Modules/ModulesResource.php`

Serializa um único `Modules` model. Expõe apenas campos relevantes para o frontend.

```php
[
    'slug'        => string,          // ex: 'prospection'
    'name'        => string,          // label i18n via ModulesEnum
    'icon'        => string|null,
    'description' => string|null,
    'order'       => int,
    'active'      => bool,
    'submodules'  => [                // [] se sem submódulos
        ['slug' => 'terrains', 'label' => 'Terrenos'],
        ['slug' => 'maps', 'label' => 'Mapas'],
    ],
]
```

---

## Integração com Planos e Feature Gating

O módulo define o **que existe** no sistema. O **plan feature gating** (via `check.feature` middleware) controla o que o tenant pode **acessar** dentro do módulo.

Exemplo: o módulo `viability` pode existir no sistema, mas a feature `viabilities.enabled` do plano pode estar desabilitada para o tenant. O middleware `check.feature:viabilities.enabled` bloqueia o acesso ao submódulo.

A relação entre módulos e features de plano é semântica (por convenção de nomes) — não há FK entre as tabelas.

---

## Integração com Permissões (RBAC)

O `ModulesEnum` é a fonte de verdade para o catálogo de permissões no tenant:

```
{módulo}.{nível}              → ex: dashboard.viewer, dashboard.editor
{módulo}.{submódulo}.{nível}  → ex: prospection.terrains.editor
```

O `TenantAclSyncService` e `ApplyRbacTemplatesCommand` usam `ModulesEnum::cases()` para gerar e sincronizar todas as permissões + roles de cada tenant.

O `PermissionNameResolver` usa `ModulesEnum::modelMap()` para resolver a permissão necessária dado um Model.

---

## Arquivos Criados/Modificados

| Arquivo | Tipo | Descrição |
|---|---|---|
| `app/Enums/Common/ModulesEnum.php` | Modificado | Refatorado: sector, models, submodules, modelMap, hasSubmodules |
| `app/Enums/Common/SectorsEnum.php` | Novo | Enum de setores com label e order |
| `app/Enums/Common/SubmodulesEnum.php` | Novo | Enum de submódulos com label e module() |
| `app/Models/Central/Modules/Modules.php` | Novo | Model central para módulos (CentralConnection) |
| `app/Services/Modules/ModulesService.php` | Novo | Serviço para buscar/agrupar módulos |
| `app/Http/Controllers/Api/V1/Tenant/Common/ModulesController.php` | Novo | Controller para rota pública /modules |
| `app/Http/Resources/Tenant/Modules/ModulesResource.php` | Novo | Resource por módulo (sem campos sensíveis) |
| `database/migrations/2026_03_14_181934_create_modules_table.php` | Novo | Migration da tabela modules |
| `database/seeders/ModulesSeeder.php` | Novo | Seeder que popula módulos a partir do enum |
