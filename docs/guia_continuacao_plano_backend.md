# Guia de Continuação — Plano de Correção Completa do Backend

Data de atualização: 2026-04-23

## Objetivo

Registrar o estado consolidado do plano de correção do backend Laravel, destacando o que já foi feito, o que ainda falta e qual ordem seguir sem perder contexto.

---

## Snapshot atual

- `php artisan test`: último snapshot completo registrado estava verde com **300 testes passando, 1078 assertions**.
- Validação focada mais recente: **45 testes passando, 176 assertions**.
- `validate()` inline em `app/Http/Controllers`: **0 ocorrências**.
- Estratégia em uso: refatoração incremental por domínio, sempre com testes focados após cada bloco.
- Atenção: há várias mudanças pré-existentes no worktree; **não reverter alterações de terceiros**.

---

## Status por fase

## Fase 1 — Estabilização imediata

Status: **parcialmente concluída**

Concluído:
- suíte de testes estabilizada no snapshot completo mais recente
- correções em billing/webhook com cobertura existente
- testes de IA isolados de dependência externa observável
- correções de migração/setup para fluxos tenant/central

Pendente:
- revisar warnings/risky tests pensando em PHPUnit 12
- documentar checklist final de webhook para payload parcial e idempotência
- rodar novamente `php artisan test` completo após a próxima leva de refactors

---

## Fase 2 — Segurança, autorização e contratos HTTP

Status: **avançada**

Concluído:
- `validate()` inline removido de todos os controllers
- vários fluxos migrados para `FormRequest` com `authorize()` real
- respostas críticas padronizadas com `Resource` e/ou `ApiResponseService`
- `ApiResponseService::translate()` ajustado para diferenciar translation keys de strings humanas
- arquitetura cobre controllers priorizados contra regressão de validação inline/query direta

Concluído por domínio/controlador:
- `LegalizacaoController`
- `LegalizacaoEtapaController`
- `TerrenoController`
- `TerrenoWorkflowController`
- `ViabilidadeController`
- `CommitteeController`
- `NegotiationController`
- `ContractController`
- `CorretoresExternosController`
- `MobileNotificationController`
- `MobileDeviceController`
- `CidadesController`
- `LanguageController`
- `DocumentosController`
- `AiTaskController`
- `AiWorkflowController`
- `Tenant/UserController`
- `AdminController@login`
- `AiController@chat`
- `RoleController` e `PermissionController` nas mutações

Pendente:
- revisar requests que ainda usam `authorize() => true` e separar casos públicos legítimos de endpoints autenticados
- padronizar completamente resources/respostas em controllers legados ainda não migrados
- revisar consistência de payload entre request, service e persistência nos módulos restantes

Requests com `return true;` que ainda precisam revisão:
- públicos provavelmente aceitáveis, mas devem ser confirmados: `LoginRequest`, `SignupRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`, `ExchangeTicketRequest`, `SelectTenantRequest`
- autenticados/tenant a revisar: `ListMobileNotificationsRequest`, `SalvarTermoDeUsoVersaoRequest`, `StoreTerrenoProdutoRequest`, `StoreProjetoRequest` ✅, `UpdateProjetoRequest` ✅, `MarkProjetoReadyRequest` ✅, `StoreProprietarioRequest` ✅, `UpdateProprietarioRequest` ✅, `StoreVeiculoRequest`, `UpdateVeiculoRequest`, `StoreRequisicaoVeiculoRequest`

**Correções aplicadas (2026-04-23):**
- `StoreProdutoRequest` → `Gate::allows('create', Produto::class)`
- `UpdateProdutoRequest` → `Gate::allows('update', Produto::class)`
- `StoreRegionalRequest` → `Gate::allows('create', Regional::class)`
- `UpdateRegionalRequest` → `Gate::allows('update', Regional::class)`
- `UpdateTerrenoProdutoRequest` → `Gate::allows('update', TerrenoProduto::class)`
- `StoreProprietarioRequest` → `Gate::allows('create', Proprietario::class)`
- `UpdateProprietarioRequest` → `Gate::allows('update', Proprietario::class)`
- `StoreProjetoRequest` → `Gate::allows('create', Projeto::class)`
- `UpdateProjetoRequest` → `Gate::allows('update', Projeto::class)`
- `MarkProjetoReadyRequest` → `Gate::allows('create', Projeto::class)`

---

## Fase 3 — Convergência arquitetural

Status: **em andamento avançado**

Concluído:
- repositories adotados em domínios críticos:
  - `TerrenoRepository`
  - `CommitteeRepository`
  - `NegotiationRepository`
  - `ContractRepository`
  - `LegalizacaoRepository`
  - `LegalizacaoEtapaRepository`
  - `CorretorExternoRepository`
  - `ViabilidadeRepository`
  - `DocumentoRepository`
  - `TaskRepository`
  - `CidadeRepository`
  - `UserRepository` tenant
  - `DepartmentRepository`
  - `PositionRepository`
  - `PlanRepository`
  - `AiMonitorService` (queries migradas do AiMonitorController)
  - `ProprietarioRepository` + `ProprietarioService` (criados para ProprietariosController)
- services ajustados para orquestrar regra e delegar persistência nos domínios já migrados
- `PlanRepositoryInterface` ampliado com `findActiveBySlug()`
- `CentralUserRepositoryInterface` ampliado com `findByEmail()`
- `DepartmentController`, `PositionController`, `UserManagementController` e `PlanSwapController` tiveram queries diretas removidas
- `DocumentosController` passou a delegar upload/storage a `DocumentoService`

Pendente:
- continuar removendo queries Eloquent diretas em controllers ainda legados
- revisar uso de Route Model Binding nos endpoints ainda baseados em `id` manual
- avaliar hooks de model com regra de negócio e mover para service/event quando fizer sentido
- revisar domains com alto acoplamento restantes: produtos, regionais, terreno-produtos, projetos, proprietários, dashboards/admin, exports e webhook

Controllers com queries diretas ainda detectadas:
- Central/admin: `Admin/AclController`, `Admin/AuditController`, `Admin/DashboardController` ✅ (via DashboardService), `AdminController@dashboard` ✅, `AuthController`, `PlanController`, `PublicTenantController`, `SignupController`, `WebhookController` ✅
- Tenant admin RBAC: `RoleController` ✅ (migrado), `PermissionController` ✅ (migrado)
- Tenant IA: `AiPredictiveAnalysisController` ✅ (migrado), `AiScoringController` ✅ (migrado), `AiMonitorController` ✅ (migrado)
- Tenant dados/produto: `ProdutosController` ✅ (migrado), `RegionaisController` ✅ (migrado), `ProprietariosController` ✅ (migrado), `TerrenoProdutosController` ✅ (migrado)
- Tenant projetos/export: `ProjetoController` ✅ (via service/repository), `TerrenosExportController` ✅ (migrado)

Próximos bons alvos (legados restantes):
1. `AclController` e `AuditController` — tenant admin RBAC legado
2. `AuthController`, `PlanController`, `PublicTenantController`, `SignupController` — central auth/admin
3. `AdminController@dashboard` — via DashboardService (já migrado, verificar)

---

## Fase 4 — Governança, qualidade e manutenção

Status: **iniciada**

Concluído:
- `tests/Architecture/AdminControllerArchitectureTest.php` ampliado para controllers priorizados
- `tests/Architecture/TenantAdminRequestAuthorizationTest.php` cobre requests tenant-admin contra `return true;`
- PHPStan nível 8 está operacional com baseline para erros existentes
- revisão de migrations sem `down()` funcional registrada como OK no fluxo anterior

Pendente:
- ampliar testes de arquitetura para todos os controllers remanescentes com query direta
- definir formalmente governança Pest/PHPUnit no projeto
- rodar `vendor/bin/phpstan analyse` completo e atualizar baseline apenas se necessário
- revisar rotas customizadas críticas para naming e binding
- atualizar documentação técnica final pós-refactor

---

## Blocos concluídos recentemente

### Legalização etapas e mobile

Concluído:
- criado `LegalizacaoEtapaRepository`
- criados requests dedicados para list/show/destroy/reorder/status/store/update de etapas
- `LegalizacaoEtapaController` refatorado para controller thin
- `LegalizacaoService` passou a usar repository em etapas/dependências
- `MobileDeviceController` migrado para `StoreMobileDeviceRequest`, `DestroyMobileDeviceRequest` e `MobileDeviceInstallationResource`
- testes adicionados em `LegalizacaoEtapaApiTest` e `MobileDeviceApiTest`

Validação:
```bash
php artisan test tests/Feature/Tenant/MobileDeviceApiTest.php tests/Feature/Tenant/LegalizacaoEtapaApiTest.php
```

Resultado: **15 testes passando, 45 assertions**.

### Cidades, locale, roles e permissões

Concluído:
- `CidadesController` migrado para `BuscarCidadesRequest`, `DadosCidadeRequest`, resources e `CidadeRepository`
- `LanguageController` migrado para `SetLocaleRequest` e `LocaleResource`
- `RoleController` e `PermissionController` passaram a usar FormRequests nas mutações
- requests de role/permissão deixaram de usar autorização trivial
- `TenantAdminRequestAuthorizationTest` ampliado

Validação:
```bash
php artisan test tests/Architecture/TenantAdminRequestAuthorizationTest.php tests/Feature/Tenant/CidadesApiTest.php tests/Feature/Tenant/LocaleApiTest.php
```

Resultado: **6 testes passando, 45 assertions**.

### Documentos, IA e usuários

Concluído:
- `DocumentosController` migrado para `ListDocumentosRequest`, `StoreDocumentoRequest`, `UpdateDocumentoRequest`, `DocumentoRepository` e `DocumentoService`
- `AiTaskController` e `AiWorkflowController` migrados para FormRequests e repositories
- `Tenant/UserController` migrado para `StoreTenantUserRequest`, `UpdateTenantUserRequest` e `UserRepository`
- `AdminController@login` migrado para `LoginRequest` e `CentralUserRepositoryInterface::findByEmail`
- `AiController@chat` migrado para `ChatAiRequest`
- `validate()` inline em controllers zerado

Validação:
```bash
php artisan test tests/Feature/Tenant/AiAutomationApiTest.php tests/Feature/Tenant/DocumentosApiTest.php tests/Feature/Tenant/CidadesApiTest.php tests/Feature/Tenant/LocaleApiTest.php tests/Architecture/TenantAdminRequestAuthorizationTest.php
```

Resultado: **10 testes passando, 77 assertions**.

### Admin tenant people e PlanSwap

Concluído:
- queries diretas removidas de `DepartmentController`, `PositionController` e `UserManagementController`
- `DepartmentService`, `PositionService` e `TenantUserService` ampliados para buscar entidades por repository
- `UserRepository` tenant expandido para busca com relações e select
- query direta removida de `PlanSwapController`
- `AdminControllerArchitectureTest` ampliado para bloquear regressões

Validação:
```bash
php artisan test tests/Architecture/AdminControllerArchitectureTest.php tests/Feature/Tenant/Admin/UserManagementWithDepartmentPositionTest.php tests/Feature/Tenant/Admin/DepartmentTest.php tests/Feature/Tenant/Admin/PositionTest.php
```

Resultado: **45 testes passando, 176 assertions**.

---

## Comandos úteis de continuidade

```bash
# verificar se voltou validação inline em controllers
rg -- '->validate\(|validate\(' app/Http/Controllers

# listar queries diretas remanescentes em controllers
rg '::(query|where|create|find|findOrFail|with|paginate|all|get|count|latest)\(' app/Http/Controllers

# listar requests com autorização trivial
rg 'return true;' app/Http/Requests

# testes focados mais recentes
php artisan test tests/Architecture/AdminControllerArchitectureTest.php tests/Architecture/TenantAdminRequestAuthorizationTest.php
php artisan test tests/Feature/Tenant/Admin/UserManagementWithDepartmentPositionTest.php tests/Feature/Tenant/Admin/DepartmentTest.php tests/Feature/Tenant/Admin/PositionTest.php
php artisan test tests/Feature/Tenant/AiAutomationApiTest.php tests/Feature/Tenant/DocumentosApiTest.php
```

---

## Critérios de aceite atualizados

- `validate()` inline em controllers: **OK**
- controllers dos domínios críticos já migrados sem query direta: **parcial avançado**
- `FormRequest` em mutações críticas: **parcial avançado**
- resources/respostas padronizadas: **parcial**
- arquitetura cobrindo regressões principais: **parcial**
- suite completa recente após todos os refactors: **pendente**
- PHPStan nível 8 completo pós-refactor: **pendente**

---

## Observações para continuidade segura

- não instalar pacotes sem aprovação explícita
- não reverter alterações não relacionadas já presentes no worktree
- continuar em blocos pequenos por domínio
- após cada bloco, rodar teste feature do domínio e arquitetura relevante
- preferir recursos nativos do Laravel e manter Controller → Service → Repository
