# Auditoria RBAC das rotas tenant

Gerado por `php scripts/security/audit_tenant_rbac.php`.

Critério: rotas tenant mutáveis (`POST`, `PUT`, `PATCH` e `DELETE`) em `routes/tenant.php` devem ter ao menos um destes sinais objetivos: `permission.gate`, `tenant.admin`, `FormRequest::authorize()` com checagem de permissão, papel ou self-service autenticado, ou autorização explícita no controller. Rotas públicas/de ciclo de autenticação são excluídas do escopo de RBAC.

## Resumo

- Rotas mutáveis analisadas: 58
- Rotas com cobertura objetiva: 58
- Rotas para revisão manual: 0

## Rotas Para Revisão Manual

Nenhuma rota mutável sem cobertura objetiva foi encontrada.

## Rotas Cobertas

| Método | Rota | Controller | Sinais |
| --- | --- | --- | --- |
| `PUT` | `/auth/me` | `TenantAuthController@updateMe` | `FormRequest::authorize(authenticated)` |
| `PUT` | `/locale` | `LanguageController@set` | `FormRequest::authorize(authenticated)` |
| `POST` | `/tenant/billing-portal` | `TenantController@billingPortal` | `tenant.admin` |
| `POST` | `/tenant/subscription/swap` | `PlanSwapController@swap` | `tenant.admin`, `FormRequest::authorize(role)` |
| `POST` | `/tenant/billing/setup-intent` | `TenantController@createSetupIntent` | `tenant.admin` |
| `POST` | `/tenant/billing/payment-method` | `TenantController@updateDefaultPaymentMethod` | `tenant.admin`, `FormRequest::authorize(authenticated)` |
| `POST` | `/tenant/billing/coupon/redeem` | `TenantCouponController@redeem` | `tenant.admin` |
| `POST` | `/tenant/billing/retry-payment` | `DunningController@retryPayment` | `tenant.admin` |
| `POST` | `users` | `AdminUserManagementController@store` | `tenant.admin` |
| `PUT` | `users/{id}/module-permissions` | `AdminUserManagementController@updateModulePermissions` | `tenant.admin` |
| `POST` | `/terrenos` | `TerrenoController@store` | `permission.gate`, `FormRequest::authorize(permission)`, `controller authorize` |
| `POST` | `/terrenos/{id}/informacoes` | `TerrenoController@storeInfo` | `FormRequest::authorize(permission)`, `controller authorize` |
| `PUT` | `/terrenos/informacoes/{infoId}` | `TerrenoController@updateInfo` | `FormRequest::authorize(permission)`, `controller authorize` |
| `DELETE` | `/terrenos/informacoes/{infoId}` | `TerrenoController@destroyInfo` | `controller authorize` |
| `POST` | `/terrenos/{id}/workflow` | `TerrenoWorkflowController@update` | `FormRequest::authorize(permission)` |
| `PUT` | `/terrenos/{id}/qualificacao` | `TerrenoWorkflowController@updateQualification` | `FormRequest::authorize(permission)` |
| `POST` | `/terrenos/{id}/import-kmz` | `TerrenoController@importKmz` | `FormRequest::authorize(permission)`, `controller authorize` |
| `POST` | `/terrenos/{id}/recalculate-area` | `TerrenoController@recalculateArea` | `controller authorize` |
| `POST` | `/documentos` | `DocumentosController@store` | `FormRequest::authorize(permission)` |
| `POST` | `/produtos` | `ProdutosController@store` | `FormRequest::authorize(permission)` |
| `POST` | `/produtos/{produto}/restore` | `ProdutosController@restore` | `FormRequest::authorize(permission)` |
| `POST` | `/terrenos/{id}/export/check-list` | `TerrenosExportController@checklistPdf` | `controller authorize` |
| `POST` | `/viabilidades/compare` | `ViabilidadeController@compare` | `FormRequest::authorize(permission)` |
| `POST` | `/viabilidades/{id}/solicitar-aprovacao` | `ViabilidadeController@solicitarAprovacao` | `FormRequest::authorize(permission)` |
| `POST` | `/viabilidades/{id}/aprovar` | `ViabilidadeController@aprovar` | `FormRequest::authorize(permission)` |
| `POST` | `/viabilidades/{id}/reprovar` | `ViabilidadeController@reprovar` | `FormRequest::authorize(permission)` |
| `POST` | `/viabilidades/{id}/ativar` | `ViabilidadeController@ativar` | `FormRequest::authorize(permission)` |
| `POST` | `/viabilidades/{id}/duplicate` | `ViabilidadeController@duplicate` | `FormRequest::authorize(permission)` |
| `POST` | `/viabilidades/{id}/gerar-dre` | `ViabilidadeController@gerarDre` | `controller authorize` |
| `POST` | `/viabilidades/{id}/recalcular` | `ViabilidadeController@recalcular` | `FormRequest::authorize(permission)` |
| `POST` | `/viabilidades/{id}/restore` | `ViabilidadeController@restore` | `FormRequest::authorize(permission)` |
| `POST` | `/ai/sig-ai` | `AiController@chat` | `FormRequest::authorize(authenticated)` |
| `POST` | `/ai/terrenos/{id}/relatorio-pdf` | `AiTerrenoReportController@generate` | `controller authorize` |
| `POST` | `/recalculate` | `AiScoringController@recalculateAll` | `controller authorize` |
| `POST` | `/tasks` | `AiTaskController@store` | `FormRequest::authorize(permission)`, `controller authorize` |
| `PUT` | `/tasks/{taskId}` | `AiTaskController@update` | `FormRequest::authorize(permission)`, `controller authorize` |
| `POST` | `/workflow/transition` | `AiWorkflowController@transition` | `FormRequest::authorize(permission)`, `controller authorize` |
| `POST` | `/projetos/{id}/marcar-pronto-registro` | `ProjetoController@markReady` | `FormRequest::authorize(permission)`, `controller authorize` |
| `POST` | `/projetos/{id}/cancelar` | `ProjetoController@cancel` | `controller authorize` |
| `POST` | `/comite` | `CommitteeController@store` | `FormRequest::authorize(permission)` |
| `POST` | `/comite/{id}/department-reviews` | `CommitteeController@upsertDepartmentReview` | `FormRequest::authorize(permission)` |
| `POST` | `/comite/{id}/decision` | `CommitteeController@finalize` | `FormRequest::authorize(permission)` |
| `POST` | `/negociacoes` | `NegotiationController@store` | `FormRequest::authorize(permission)` |
| `PUT` | `/negociacoes/{id}` | `NegotiationController@update` | `FormRequest::authorize(permission)` |
| `POST` | `/negociacoes/{id}/events` | `NegotiationController@addEvent` | `FormRequest::authorize(permission)` |
| `POST` | `/contratos` | `ContractController@store` | `FormRequest::authorize(permission)` |
| `PUT` | `/contratos/{id}` | `ContractController@update` | `FormRequest::authorize(permission)` |
| `POST` | `/contratos/{id}/sign` | `ContractController@sign` | `FormRequest::authorize(permission)` |
| `POST` | `/devices` | `MobileDeviceController@store` | `FormRequest::authorize(authenticated)` |
| `DELETE` | `/devices/{installationId}` | `MobileDeviceController@destroy` | `FormRequest::authorize(authenticated)` |
| `POST` | `/notifications/{id}/read` | `MobileNotificationController@read` | `FormRequest::authorize(authenticated)` |
| `POST` | `/legalizacoes/{id}/sync-gantt` | `LegalizacaoController@syncGantt` | `FormRequest::authorize(permission)` |
| `POST` | `/legalizacoes/{id}/recalcular-progresso` | `LegalizacaoController@recalcularProgresso` | `FormRequest::authorize(permission)` |
| `POST` | `/` | `LegalizacaoEtapaController@store` | `FormRequest::authorize(permission)` |
| `PUT` | `/{id}` | `LegalizacaoEtapaController@update` | `FormRequest::authorize(permission)` |
| `DELETE` | `/{id}` | `LegalizacaoEtapaController@destroy` | `FormRequest::authorize(permission)` |
| `POST` | `/reorder` | `LegalizacaoEtapaController@reorder` | `FormRequest::authorize(permission)` |
| `PATCH` | `/{id}/status` | `LegalizacaoEtapaController@updateStatus` | `FormRequest::authorize(permission)` |
