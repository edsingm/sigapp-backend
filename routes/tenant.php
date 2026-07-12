<?php

use App\Http\Controllers\Api\V1\CidadesController;
use App\Http\Controllers\Api\V1\LanguageController;
use App\Http\Controllers\Api\V1\MunicipioController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\Tenant\ActivityController;
use App\Http\Controllers\Api\V1\Tenant\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Api\V1\Tenant\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Api\V1\Tenant\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Api\V1\Tenant\Admin\UserManagementController as AdminUserManagementController;
use App\Http\Controllers\Api\V1\Tenant\AiController;
use App\Http\Controllers\Api\V1\Tenant\AiGeneratedReportController;
use App\Http\Controllers\Api\V1\Tenant\AiMonitorController;
use App\Http\Controllers\Api\V1\Tenant\AiPredictiveAnalysisController;
use App\Http\Controllers\Api\V1\Tenant\AiScoringController;
use App\Http\Controllers\Api\V1\Tenant\AiTaskController;
use App\Http\Controllers\Api\V1\Tenant\AiTerrenoReportController;
use App\Http\Controllers\Api\V1\Tenant\AiWorkflowController;
use App\Http\Controllers\Api\V1\Tenant\BillingHistoryController;
use App\Http\Controllers\Api\V1\Tenant\CommitteeAiDossierController;
use App\Http\Controllers\Api\V1\Tenant\CommitteeController;
use App\Http\Controllers\Api\V1\Tenant\CommitteeMeetingController;
use App\Http\Controllers\Api\V1\Tenant\Common\ModulesController;
use App\Http\Controllers\Api\V1\Tenant\ContextualAiController;
use App\Http\Controllers\Api\V1\Tenant\ContractController;
use App\Http\Controllers\Api\V1\Tenant\CorretoresExternosController;
use App\Http\Controllers\Api\V1\Tenant\CouponController as TenantCouponController;
use App\Http\Controllers\Api\V1\Tenant\DashboardController;
use App\Http\Controllers\Api\V1\Tenant\DocumentIntelligenceController;
use App\Http\Controllers\Api\V1\Tenant\DocumentosController;
use App\Http\Controllers\Api\V1\Tenant\DunningController;
use App\Http\Controllers\Api\V1\Tenant\GlobalSearchController;
use App\Http\Controllers\Api\V1\Tenant\LegalizacaoController;
use App\Http\Controllers\Api\V1\Tenant\LegalizacaoEtapaController;
use App\Http\Controllers\Api\V1\Tenant\LegalizacaoInsightController;
use App\Http\Controllers\Api\V1\Tenant\MobileCaptureController;
use App\Http\Controllers\Api\V1\Tenant\MobileDeviceController;
use App\Http\Controllers\Api\V1\Tenant\MobileNotificationController;
use App\Http\Controllers\Api\V1\Tenant\NegotiationController;
use App\Http\Controllers\Api\V1\Tenant\NegotiationDealRoomController;
use App\Http\Controllers\Api\V1\Tenant\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Tenant\PlanSwapController;
use App\Http\Controllers\Api\V1\Tenant\PremissasViabilidadeController;
use App\Http\Controllers\Api\V1\Tenant\ProdutosController;
use App\Http\Controllers\Api\V1\Tenant\ProjetoController;
use App\Http\Controllers\Api\V1\Tenant\ProjetoPlanningController;
use App\Http\Controllers\Api\V1\Tenant\ProprietariosController;
use App\Http\Controllers\Api\V1\Tenant\RegionaisController;
use App\Http\Controllers\Api\V1\Tenant\ReportBuilderController;
use App\Http\Controllers\Api\V1\Tenant\SavedViewController;
use App\Http\Controllers\Api\V1\Tenant\ShortlistController;
use App\Http\Controllers\Api\V1\Tenant\TaskController;
use App\Http\Controllers\Api\V1\Tenant\TenantController;
use App\Http\Controllers\Api\V1\Tenant\TerrenoController;
use App\Http\Controllers\Api\V1\Tenant\TerrenoProdutosController;
use App\Http\Controllers\Api\V1\Tenant\TerrenosExportController;
use App\Http\Controllers\Api\V1\Tenant\TerrenoWorkflowController;
use App\Http\Controllers\Api\V1\Tenant\TimelineController;
use App\Http\Controllers\Api\V1\Tenant\UserController;
use App\Http\Controllers\Api\V1\Tenant\UserOnboardingController;
use App\Http\Controllers\Api\V1\Tenant\UserPreferencesController;
use App\Http\Controllers\Api\V1\Tenant\ViabilidadeController;
use App\Http\Controllers\Api\V1\Tenant\ViabilidadeScenarioController;
use App\Http\Controllers\Api\V1\TenantAuthController;
use App\Http\Controllers\Api\V1\TenantPasswordResetController;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SetUserLocale;
use App\Models\Central\Tenant as CentralTenant;
use App\Services\HealthCheckService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Tenant Application
|--------------------------------------------------------------------------
|
| Routes for tenant-specific operations.
| These are accessible at: https://{tenant}.sigapp.com.br/api/v1/...
|
*/

Route::middleware([
    ForceJsonResponse::class,
    AddTenantContextToLogs::class,
    ApiRequestLogger::class,
])->group(function () {

    Route::prefix('api/v1')->group(function () {

        // Public tenant routes
        Route::middleware(['tenant.context', 'throttle:api-public'])->group(function () {

            // Auth - Login for tenant
            Route::post('/auth/login', [TenantAuthController::class, 'login']);
            Route::post('/auth/exchange-ticket', [TenantAuthController::class, 'exchangeTicket'])
                ->middleware('throttle:transfer-ticket');
            Route::post('/auth/password/forgot', [TenantPasswordResetController::class, 'forgotPassword'])
                ->middleware('throttle:password-reset-request');
            Route::post('/auth/password/reset', [TenantPasswordResetController::class, 'resetPassword'])
                ->middleware('throttle:password-reset-submit');
        });
        // Authenticated tenant routes (always accessible after login)
        Route::middleware([
            'tenant.context',
            'auth:sanctum',
            'auth.tenant',
            'throttle:api-auth',
            SetUserLocale::class,
        ])->group(function () {

            // Auth
            Route::post('/auth/logout', [TenantAuthController::class, 'logout']);
            Route::post('/auth/logout-all', [TenantAuthController::class, 'logoutAll']);
            Route::post('/auth/refresh', [TenantAuthController::class, 'refresh']);
            Route::get('/auth/me', [TenantAuthController::class, 'me']);
            Route::put('/auth/me', [TenantAuthController::class, 'updateMe']);

            // Locale
            Route::put('/locale', [LanguageController::class, 'set']);

            // Preferências de notificação do usuário
            Route::get('/me/notification-preferences', [NotificationPreferenceController::class, 'index']);
            Route::put('/me/notification-preferences', [NotificationPreferenceController::class, 'update']);
            Route::put('/me/notification-settings', [NotificationPreferenceController::class, 'updateSettings']);

            // Bootstrap: modules, plan and user RBAC for navbar/feature gating
            Route::get('/start', [ModulesController::class, 'index']);
            Route::get('/modules', [ModulesController::class, 'modules']);

            Route::get('/tenant/subscription', [TenantController::class, 'subscription'])
                ->middleware('tenant.admin');
            Route::post('/tenant/billing-portal', [TenantController::class, 'billingPortal'])
                ->middleware('tenant.admin');

            // Catálogo de planos acessível no domínio do tenant (a rota pública
            // /plans é central-only). Usado pela tela de faturamento para montar
            // as opções de upgrade/downgrade. Admin-only.
            Route::get('/tenant/plans', [PlanController::class, 'index'])
                ->middleware('tenant.admin');

            // Billing — troca de plano e atualização de método de pagamento
            // Acessíveis mesmo com assinatura suspensa (tenant pode reativar/atualizar sem bloqueio)
            Route::middleware('tenant.admin')->group(function () {
                Route::post('/tenant/subscription/swap', [PlanSwapController::class, 'swap'])
                    ->middleware('tenant.admin');
                Route::post('/tenant/billing/setup-intent', [TenantController::class, 'createSetupIntent'])
                    ->middleware('tenant.admin');
                Route::post('/tenant/billing/payment-method', [TenantController::class, 'updateDefaultPaymentMethod'])
                    ->middleware('tenant.admin');
                Route::post('/tenant/billing/coupon/redeem', [TenantCouponController::class, 'redeem'])
                    ->middleware('tenant.admin');
                Route::get('/tenant/billing/payment-status', [DunningController::class, 'status']);
                Route::post('/tenant/billing/retry-payment', [DunningController::class, 'retryPayment'])
                    ->middleware('tenant.admin');
            });

            Route::middleware(CheckSubscriptionStatus::class)->group(function () {

                // Tenant info
                Route::get('/tenant', [TenantController::class, 'show']);
                Route::get('/tenant/usage', [TenantController::class, 'usage']);

                // Billing history
                Route::prefix('tenant/billing')->group(function () {
                    Route::get('/history', [BillingHistoryController::class, 'index']);
                    Route::get('/invoices/{invoiceId}', [BillingHistoryController::class, 'show']);
                    Route::get('/invoices/{invoiceId}/pdf', [BillingHistoryController::class, 'downloadPdf']);
                });

                // Users (select inputs for tenant forms)
                Route::get('/users/for-select', [UserController::class, 'usersForSelect']);

                Route::get('/me/preferences', [UserPreferencesController::class, 'show']);
                Route::patch('/me/preferences', [UserPreferencesController::class, 'update']);
                Route::middleware('check.feature:onboarding.profile')->prefix('me/onboarding')->group(function () {
                    Route::get('/', [UserOnboardingController::class, 'show']);
                    Route::post('/events', [UserOnboardingController::class, 'event']);
                    Route::post('/dismiss', [UserOnboardingController::class, 'dismiss']);
                    Route::post('/resume', [UserOnboardingController::class, 'resume']);
                });

                Route::middleware('check.feature:search.global')->group(function () {
                    Route::get('/search', [GlobalSearchController::class, 'index']);
                });

                Route::middleware('check.feature:reports.builder')->prefix('reports')->group(function () {
                    Route::get('/templates', [ReportBuilderController::class, 'templates']);
                    Route::post('/templates', [ReportBuilderController::class, 'storeTemplate']);
                    Route::get('/templates/{template}', [ReportBuilderController::class, 'showTemplate'])->whereNumber('template');
                    Route::put('/templates/{template}', [ReportBuilderController::class, 'updateTemplate'])->whereNumber('template');
                    Route::delete('/templates/{template}', [ReportBuilderController::class, 'destroyTemplate'])->whereNumber('template');
                    Route::post('/runs', [ReportBuilderController::class, 'storeRun']);
                    Route::get('/runs/{run}', [ReportBuilderController::class, 'showRun'])->whereNumber('run');
                    Route::get('/runs/{run}/download', [ReportBuilderController::class, 'download'])->whereNumber('run');
                });

                Route::middleware('check.feature:workspace.saved_views')->group(function () {
                    Route::get('/saved-views', [SavedViewController::class, 'index']);
                    Route::post('/saved-views', [SavedViewController::class, 'store']);
                    Route::get('/saved-views/{id}', [SavedViewController::class, 'show'])->whereNumber('id');
                    Route::put('/saved-views/{id}', [SavedViewController::class, 'update'])->whereNumber('id');
                    Route::delete('/saved-views/{id}', [SavedViewController::class, 'destroy'])->whereNumber('id');
                    Route::post('/saved-views/{id}/set-default', [SavedViewController::class, 'setDefault'])->whereNumber('id');
                });

                // Atividades unificadas — a feature permanece desligada até o
                // slice colaborativo estar liberado por plano.
                Route::middleware('check.feature:collaboration.inbox')->group(function () {
                    Route::get('/activities', [ActivityController::class, 'index'])
                        ->middleware('permission.gate:prospection,terrains');
                    Route::get('/activities/{entityType}/{entityId}', [ActivityController::class, 'forEntity'])
                        ->whereNumber('entityId')
                        ->middleware('permission.gate:prospection,terrains');
                });

                // Tarefas colaborativas. O middleware de permissão preserva a
                // ACL de prospecção até existir um módulo collaboration próprio.
                Route::middleware('check.feature:collaboration.tasks')
                    ->middleware('permission.gate:prospection,terrains')
                    ->prefix('tasks')
                    ->group(function () {
                        Route::get('/my-queue', [TaskController::class, 'myQueue']);
                        Route::get('/', [TaskController::class, 'index']);
                        Route::post('/', [TaskController::class, 'store']);
                        Route::get('/{task}', [TaskController::class, 'show']);
                        Route::put('/{task}', [TaskController::class, 'update']);
                        Route::delete('/{task}', [TaskController::class, 'destroy']);
                        Route::get('/{task}/comments', [TaskController::class, 'listComments']);
                        Route::post('/{task}/comments', [TaskController::class, 'comments']);
                    });

                Route::middleware([
                    'check.feature:prospection.comparison',
                    'permission.gate:prospection,terrains',
                ])->group(function () {
                    Route::get('/shortlists', [ShortlistController::class, 'index']);
                    Route::post('/shortlists', [ShortlistController::class, 'store']);
                    Route::get('/shortlists/{shortlist}', [ShortlistController::class, 'show']);
                    Route::put('/shortlists/{shortlist}', [ShortlistController::class, 'update']);
                    Route::delete('/shortlists/{shortlist}', [ShortlistController::class, 'destroy']);
                    Route::post('/shortlists/{shortlist}/items', [ShortlistController::class, 'addItem']);
                    Route::delete('/shortlists/{shortlist}/items/{terreno}', [ShortlistController::class, 'removeItem']);
                });

                // Tenant admin (users, roles and permissions)
                Route::prefix('tenant-admin')
                    ->middleware('tenant.admin')
                    ->as('tenant-admin.')
                    ->group(function () {
                        Route::post('users', [AdminUserManagementController::class, 'store'])
                            ->middleware('enforce.limits:users')
                            ->middleware('tenant.admin')
                            ->name('tenant-admin.users.store');
                        Route::apiResource('users', AdminUserManagementController::class)->except(['store']);
                        Route::post('users/{id}/send-invite', [AdminUserManagementController::class, 'sendInvite'])
                            ->middleware('tenant.admin')
                            ->name('tenant-admin.users.send-invite');
                        Route::put('users/{id}/module-permissions', [AdminUserManagementController::class, 'updateModulePermissions'])
                            ->middleware('tenant.admin')
                            ->name('tenant-admin.users.module-permissions');
                        Route::get('roles/select', [AdminRoleController::class, 'forSelect'])
                            ->name('tenant-admin.roles.select');
                        Route::apiResource('roles', AdminRoleController::class)
                            ->middleware('permission.gate:admin');
                        Route::apiResource('permissions', AdminPermissionController::class)
                            ->middleware('permission.gate:admin');

                        // Departments
                        Route::get('departments/select', [AdminDepartmentController::class, 'forSelect'])
                            ->name('tenant-admin.departments.select');
                        Route::apiResource('departments', AdminDepartmentController::class)
                            ->middleware('permission.gate:admin');
                    });

                // Terrenos (with plan limit enforcement)
                Route::middleware(['check.feature:prospection', 'enforce.limits:terrenos'])->group(function () {
                    Route::post('/terrenos', [TerrenoController::class, 'store'])
                        ->middleware('permission.gate:prospection,terrains');
                });
                // Rotas específicas devem vir ANTES do apiResource
                Route::middleware('check.feature:prospection')->group(function () {
                    Route::post('/terrenos/compare', [ShortlistController::class, 'compare'])
                        ->middleware([
                            'check.feature:prospection.comparison',
                            'permission.gate:prospection,terrains',
                        ]);
                    Route::get('/terrenos/pipeline', [TerrenoController::class, 'pipeline'])
                        ->middleware([
                            'check.feature:prospection.pipeline_board',
                            'permission.gate:prospection,terrains',
                        ]);
                    Route::get('/terrenos/filter', [TerrenoController::class, 'filter']);
                    Route::get('/terrenos/select', [TerrenoController::class, 'forSelect']);
                    Route::get('/terrenos/{id}/informacoes', [TerrenoController::class, 'getInformacoes'])
                        ->middleware('permission.gate:prospection,terrains');
                    Route::post('/terrenos/{id}/informacoes', [TerrenoController::class, 'storeInfo']);
                    Route::put('/terrenos/informacoes/{infoId}', [TerrenoController::class, 'updateInfo']);
                    Route::delete('/terrenos/informacoes/{infoId}', [TerrenoController::class, 'destroyInfo']);
                    Route::get('/terrenos/{id}/workflow', [TerrenoWorkflowController::class, 'show']);
                    Route::get('/terrenos/{id}/workflow-state', [TerrenoWorkflowController::class, 'workflowState']);
                    Route::get('/terrenos/{id}/readiness', [TerrenoWorkflowController::class, 'readiness']);
                    Route::post('/terrenos/{id}/workflow', [TerrenoWorkflowController::class, 'update']);
                    Route::put('/terrenos/{id}/qualificacao', [TerrenoWorkflowController::class, 'updateQualification']);
                    Route::post('/terrenos/{id}/import-kmz', [TerrenoController::class, 'importKmz']);
                    Route::post('/terrenos/{id}/recalculate-area', [TerrenoController::class, 'recalculateArea']);
                    Route::get('/terrenos/{id}/timeline', [TimelineController::class, 'index']);
                    Route::apiResource('terrenos', TerrenoController::class)->except(['store']);
                });

                // Documentos
                Route::prefix('documentos')->group(function () {
                    Route::middleware('check.feature:documents.intelligence')->group(function () {
                        Route::get('/requirements', [DocumentIntelligenceController::class, 'requirements']);
                        Route::get('/{documento}/versions', [DocumentIntelligenceController::class, 'versions'])->whereNumber('documento');
                        Route::post('/{documento}/versions', [DocumentIntelligenceController::class, 'storeVersion'])->whereNumber('documento');
                        Route::get('/{documento}/analysis', [DocumentIntelligenceController::class, 'analysis'])->whereNumber('documento');
                        Route::post('/{documento}/analysis', [DocumentIntelligenceController::class, 'requestAnalysis'])->whereNumber('documento');
                        Route::post('/{documento}/reviews', [DocumentIntelligenceController::class, 'review'])->whereNumber('documento');
                    });
                    Route::get('/tipos', [DocumentosController::class, 'tipos']);
                    Route::get('/categorias', [DocumentosController::class, 'categorias']);
                    Route::get('/{id}/view', [DocumentosController::class, 'view']);
                    Route::get('/{id}/download', [DocumentosController::class, 'download']);
                });
                Route::post('/documentos', [DocumentosController::class, 'store'])
                    ->middleware('enforce.limits:storage_gb');
                Route::apiResource('documentos', DocumentosController::class)->except(['store']);

                // Corretores Externos
                Route::get('/corretores-externos/select', [CorretoresExternosController::class, 'corretoresForSelect']);
                Route::apiResource('corretores-externos', CorretoresExternosController::class);

                // Regionais
                Route::middleware('check.feature:regionals')->group(function () {
                    Route::get('/regionais/select', [RegionaisController::class, 'forSelect']);
                    Route::apiResource('regionais', RegionaisController::class);
                });

                // Produtos
                Route::middleware('check.feature:product_settings')->group(function () {
                    Route::get('/produtos/select', [ProdutosController::class, 'forSelect']);
                    Route::get('/produtos/{produto}/historico', [ProdutosController::class, 'history']);
                    Route::post('/produtos', [ProdutosController::class, 'store'])
                        ->middleware('enforce.limits:products');
                    Route::apiResource('produtos', ProdutosController::class)->except(['store']);
                    Route::post('/produtos/{produto}/restore', [ProdutosController::class, 'restore']);
                });

                // Proprietarios
                Route::get('/proprietarios/select', [ProprietariosController::class, 'proprietariosForSelect']);
                Route::apiResource('proprietarios', ProprietariosController::class);

                // Terreno Produtos
                Route::get('/terreno-produtos/by-terreno/{terrenoId}', [TerrenoProdutosController::class, 'byTerreno']);
                Route::apiResource('terreno-produtos', TerrenoProdutosController::class);

                // Terreno Export
                Route::get('/terrenos/export/pdf', [TerrenosExportController::class, 'exportPdf'])
                    ->middleware('check.feature:exports.pdf');
                Route::get('/terrenos/export/excel', [TerrenosExportController::class, 'exportExcel'])
                    ->middleware('check.feature:exports.excel');
                Route::get('/terrenos/{id}/export/pdf-detalhe', [TerrenosExportController::class, 'exportSinglePdf'])
                    ->middleware('check.feature:exports.pdf');
                Route::post('/terrenos/{id}/export/check-list', [TerrenosExportController::class, 'checklistPdf'])
                    ->middleware('check.feature:exports.pdf');
                Route::get('/terrenos/{id}/export/viabilidade', [ViabilidadeController::class, 'exportPdf'])
                    ->middleware(['check.feature:viabilities.enabled', 'check.feature:exports.pdf']);

                // Viabilidades
                Route::middleware('check.feature:viabilities.enabled')->group(function () {
                    Route::prefix('viabilidades/{viabilidade}/scenarios')
                        ->middleware('check.feature:viabilities.scenarios')
                        ->group(function () {
                            Route::get('/', [ViabilidadeScenarioController::class, 'index']);
                            Route::post('/', [ViabilidadeScenarioController::class, 'store']);
                            Route::get('/{scenario}', [ViabilidadeScenarioController::class, 'show']);
                            Route::put('/{scenario}', [ViabilidadeScenarioController::class, 'update']);
                            Route::delete('/{scenario}', [ViabilidadeScenarioController::class, 'destroy']);
                            Route::post('/{scenario}/calculate', [ViabilidadeScenarioController::class, 'calculate']);
                            Route::post('/{scenario}/promote', [ViabilidadeScenarioController::class, 'promote']);
                        });
                    Route::get('/viabilidades/for-select', [ViabilidadeController::class, 'forSelect']);
                    Route::get('/viabilidades/terreno/{terrenoId}', [ViabilidadeController::class, 'byTerreno']);
                    Route::get('/viabilidades/terreno/{terrenoId}/latest', [ViabilidadeController::class, 'latest']);
                    Route::post('/viabilidades/compare', [ViabilidadeController::class, 'compare']);
                    Route::get('/viabilidades/{id}/export-pdf', [ViabilidadeController::class, 'exportPdf'])
                        ->middleware('check.feature:exports.pdf');
                    Route::post('/viabilidades/{id}/solicitar-aprovacao', [ViabilidadeController::class, 'solicitarAprovacao'])
                        ->middleware('throttle:viabilidade-approval');
                    Route::post('/viabilidades/{id}/aprovar', [ViabilidadeController::class, 'aprovar'])
                        ->middleware('throttle:viabilidade-approval');
                    Route::post('/viabilidades/{id}/reprovar', [ViabilidadeController::class, 'reprovar'])
                        ->middleware('throttle:viabilidade-approval');
                    Route::post('/viabilidades/{id}/revogar-aprovacao', [ViabilidadeController::class, 'revogarAprovacao'])
                        ->middleware('throttle:viabilidade-approval');
                    Route::post('/viabilidades/{id}/ativar', [ViabilidadeController::class, 'ativar']);
                    Route::post('/viabilidades/{id}/duplicate', [ViabilidadeController::class, 'duplicate']);
                    Route::post('/viabilidades/{id}/gerar-dre', [ViabilidadeController::class, 'gerarDre'])
                        ->middleware('check.feature:viabilities.dre');
                    Route::post('/viabilidades/{id}/recalcular', [ViabilidadeController::class, 'recalcular']);
                    Route::post('/viabilidades/{id}/restore', [ViabilidadeController::class, 'restore']);
                    Route::apiResource('viabilidades', ViabilidadeController::class);
                });

                // Premissas de Viabilidade
                Route::middleware(['check.feature:viabilities.enabled', 'permission.gate:configurations'])->group(function () {
                    Route::get('premissas-viabilidade/{id}/historico', [PremissasViabilidadeController::class, 'history']);
                    Route::apiResource('premissas-viabilidade', PremissasViabilidadeController::class);
                });

                // AI
                Route::middleware('check.feature:ai')->group(function () {
                    Route::get('/ai/conversations', [AiController::class, 'conversations']);
                    Route::get('/ai/conversations/{id}/messages', [AiController::class, 'conversationMessages']);
                    Route::get('/ai/budget', [AiController::class, 'budgetStatus']);
                    Route::post('/ai/sig-ai', [AiController::class, 'chat'])
                        ->middleware('ai.rate_limit', 'ai.budget');
                    Route::post('/ai/terrenos/{id}/relatorio-pdf', [AiTerrenoReportController::class, 'generate'])
                        ->middleware('ai.rate_limit', 'ai.budget')
                        ->whereNumber('id');
                    Route::get('/ai/reports/{id}/download', [AiGeneratedReportController::class, 'download'])
                        ->whereNumber('id')
                        ->name('ai.reports.download');

                    // AI Scoring
                    Route::prefix('ai/scoring')->group(function () {
                        Route::get('/ranking', [AiScoringController::class, 'getRanking']);
                        Route::post('/recalculate', [AiScoringController::class, 'recalculateAll']);
                        Route::get('/{terreno_id}', [AiScoringController::class, 'getScore'])
                            ->whereNumber('terreno_id');
                    });

                    // AI Automation
                    Route::prefix('ai/automation')->group(function () {
                        Route::post('/tasks', [AiTaskController::class, 'store']);
                        Route::put('/tasks/{taskId}', [AiTaskController::class, 'update']);
                        Route::post('/workflow/transition', [AiWorkflowController::class, 'transition']);
                        Route::get('/monitor', [AiMonitorController::class, 'index']);
                    });

                    // AI Predictive Analysis
                    Route::prefix('ai/predictive')->group(function () {
                        Route::get('/approval/{terreno_id}', [AiPredictiveAnalysisController::class, 'predictApproval']);
                        Route::get('/vgv/{terreno_id}', [AiPredictiveAnalysisController::class, 'estimateVgv']);
                        Route::get('/stalling', [AiPredictiveAnalysisController::class, 'stallingForecast']);
                    });

                    Route::middleware('check.feature:ai.contextual')->group(function () {
                        Route::post('/ai/context', [ContextualAiController::class, 'context'])
                            ->middleware('ai.rate_limit', 'ai.budget');
                        Route::post('/ai/recommendations/{recommendation}/apply', [ContextualAiController::class, 'apply'])
                            ->whereNumber('recommendation');
                    });
                });

                // Projetos
                Route::middleware('check.feature:projects_room')->group(function () {
                    Route::get('/projetos/eligible-terrenos', [ProjetoController::class, 'eligibleTerrenos']);
                    Route::post('/projetos/{id}/marcar-pronto-registro', [ProjetoController::class, 'markReady']);
                    Route::post('/projetos/{id}/cancelar', [ProjetoController::class, 'cancel']);
                    Route::apiResource('projetos', ProjetoController::class)->only(['index', 'store', 'show', 'update']);

                    Route::middleware('check.feature:projects.room')->group(function () {
                        Route::get('/projetos/{projeto}/milestones', [ProjetoPlanningController::class, 'milestones']);
                        Route::post('/projetos/{projeto}/milestones', [ProjetoPlanningController::class, 'storeMilestone']);
                        Route::post('/projetos/{projeto}/milestones/reorder', [ProjetoPlanningController::class, 'reorderMilestones']);
                        Route::put('/projetos/{projeto}/milestones/{milestone}', [ProjetoPlanningController::class, 'updateMilestone'])
                            ->whereNumber('milestone');
                        Route::delete('/projetos/{projeto}/milestones/{milestone}', [ProjetoPlanningController::class, 'destroyMilestone'])
                            ->whereNumber('milestone');

                        Route::get('/projetos/{projeto}/dependencies', [ProjetoPlanningController::class, 'dependencies']);
                        Route::post('/projetos/{projeto}/dependencies', [ProjetoPlanningController::class, 'storeDependency']);
                        Route::delete('/projetos/{projeto}/dependencies/{dependency}', [ProjetoPlanningController::class, 'destroyDependency'])
                            ->whereNumber('dependency');

                        Route::get('/projetos/{projeto}/risks', [ProjetoPlanningController::class, 'risks']);
                        Route::post('/projetos/{projeto}/risks', [ProjetoPlanningController::class, 'storeRisk']);
                        Route::put('/projetos/{projeto}/risks/{risk}', [ProjetoPlanningController::class, 'updateRisk'])
                            ->whereNumber('risk');
                        Route::delete('/projetos/{projeto}/risks/{risk}', [ProjetoPlanningController::class, 'destroyRisk'])
                            ->whereNumber('risk');
                    });
                });

                // Comitê
                Route::middleware('check.feature:committee')->group(function () {
                    Route::get('/comite', [CommitteeController::class, 'index']);
                    Route::post('/comite', [CommitteeController::class, 'store']);
                    Route::get('/comite/{id}', [CommitteeController::class, 'show'])->whereNumber('id');
                    Route::get('/comite/{id}/ai-dossier', [CommitteeAiDossierController::class, 'show'])->whereNumber('id');
                    Route::post('/comite/{id}/ai-dossier/regenerate', [CommitteeAiDossierController::class, 'regenerate'])
                        ->middleware('ai.rate_limit', 'ai.budget')
                        ->whereNumber('id');
                    Route::post('/comite/{id}/department-reviews', [CommitteeController::class, 'upsertDepartmentReview'])->whereNumber('id');
                    Route::post('/comite/{id}/decision', [CommitteeController::class, 'finalize'])->whereNumber('id');

                    Route::middleware('check.feature:committee.meeting_mode')->group(function () {
                        Route::get('/comite/sessions', [CommitteeMeetingController::class, 'index']);
                        Route::post('/comite/sessions', [CommitteeMeetingController::class, 'store']);
                        Route::get('/comite/sessions/{session}', [CommitteeMeetingController::class, 'show'])
                            ->whereNumber('session');
                        Route::put('/comite/sessions/{session}', [CommitteeMeetingController::class, 'update'])
                            ->whereNumber('session');
                        Route::post('/comite/sessions/{session}/start', [CommitteeMeetingController::class, 'start'])
                            ->whereNumber('session');
                        Route::post('/comite/sessions/{session}/close', [CommitteeMeetingController::class, 'finish'])
                            ->whereNumber('session');

                        Route::get('/comite/sessions/{session}/agenda-items', [CommitteeMeetingController::class, 'agenda'])
                            ->whereNumber('session');
                        Route::post('/comite/sessions/{session}/agenda-items', [CommitteeMeetingController::class, 'storeAgenda'])
                            ->whereNumber('session');
                        Route::put('/comite/sessions/{session}/agenda-items/reorder', [CommitteeMeetingController::class, 'reorderAgenda'])
                            ->whereNumber('session');
                        Route::put('/comite/sessions/{session}/agenda-items/{item}', [CommitteeMeetingController::class, 'updateAgenda'])
                            ->whereNumber(['session', 'item']);
                        Route::delete('/comite/sessions/{session}/agenda-items/{item}', [CommitteeMeetingController::class, 'destroyAgenda'])
                            ->whereNumber(['session', 'item']);

                        Route::get('/comite/sessions/{session}/participants', [CommitteeMeetingController::class, 'participants'])
                            ->whereNumber('session');
                        Route::post('/comite/sessions/{session}/participants', [CommitteeMeetingController::class, 'storeParticipant'])
                            ->whereNumber('session');
                        Route::put('/comite/sessions/{session}/participants/{participant}', [CommitteeMeetingController::class, 'updateParticipant'])
                            ->whereNumber(['session', 'participant']);
                        Route::delete('/comite/sessions/{session}/participants/{participant}', [CommitteeMeetingController::class, 'destroyParticipant'])
                            ->whereNumber(['session', 'participant']);

                        Route::get('/comite/sessions/{session}/minutes', [CommitteeMeetingController::class, 'minutes'])
                            ->whereNumber('session');
                        Route::post('/comite/sessions/{session}/minutes', [CommitteeMeetingController::class, 'saveMinutes'])
                            ->whereNumber('session');
                        Route::put('/comite/sessions/{session}/minutes', [CommitteeMeetingController::class, 'saveMinutes'])
                            ->whereNumber('session');
                        Route::post('/comite/sessions/{session}/minutes/approve', [CommitteeMeetingController::class, 'approveMinutes'])
                            ->whereNumber('session');
                    });
                });

                // Negociação e contratos
                Route::middleware('check.feature:negotiation')->group(function () {
                    Route::get('/negociacoes', [NegotiationController::class, 'index']);
                    Route::post('/negociacoes', [NegotiationController::class, 'store']);
                    Route::get('/negociacoes/{id}', [NegotiationController::class, 'show']);
                    Route::put('/negociacoes/{id}', [NegotiationController::class, 'update']);
                    Route::post('/negociacoes/{id}/events', [NegotiationController::class, 'addEvent']);

                    Route::get('/contratos', [ContractController::class, 'index']);
                    Route::post('/contratos', [ContractController::class, 'store']);
                    Route::get('/contratos/{id}', [ContractController::class, 'show']);
                    Route::put('/contratos/{id}', [ContractController::class, 'update']);
                    Route::post('/contratos/{id}/sign', [ContractController::class, 'sign']);

                    Route::middleware('check.feature:negotiation.deal_room')->group(function () {
                        Route::get('/negociacoes/{negociacao}/offers', [NegotiationDealRoomController::class, 'offers'])
                            ->whereNumber('negociacao');
                        Route::post('/negociacoes/{negociacao}/offers', [NegotiationDealRoomController::class, 'storeOffer'])
                            ->whereNumber('negociacao');
                        Route::get('/negociacoes/{negociacao}/offers/{offer}', [NegotiationDealRoomController::class, 'showOffer'])
                            ->whereNumber(['negociacao', 'offer']);
                        Route::post('/negociacoes/{negociacao}/offers/{offer}/accept', [NegotiationDealRoomController::class, 'acceptOffer'])
                            ->whereNumber(['negociacao', 'offer']);
                        Route::post('/negociacoes/{negociacao}/offers/{offer}/reject', [NegotiationDealRoomController::class, 'rejectOffer'])
                            ->whereNumber(['negociacao', 'offer']);

                        Route::get('/negociacoes/{negociacao}/approvals', [NegotiationDealRoomController::class, 'approvals'])
                            ->whereNumber('negociacao');
                        Route::post('/negociacoes/{negociacao}/approvals', [NegotiationDealRoomController::class, 'storeApproval'])
                            ->whereNumber('negociacao');

                        Route::get('/contratos/{contrato}/conditions', [NegotiationDealRoomController::class, 'conditions'])
                            ->whereNumber('contrato');
                        Route::post('/contratos/{contrato}/conditions', [NegotiationDealRoomController::class, 'storeCondition'])
                            ->whereNumber('contrato');
                        Route::patch('/contratos/{contrato}/conditions/{condition}', [NegotiationDealRoomController::class, 'updateCondition'])
                            ->whereNumber(['contrato', 'condition']);
                    });
                });

                // Cidades e Estados
                Route::middleware('check.feature:territorial_base')->group(function () {
                    Route::get('/cidades/estados', [CidadesController::class, 'index']);
                    Route::get('/cidades/buscar', [CidadesController::class, 'buscar']);
                    Route::get('/cidades/dados', [CidadesController::class, 'dados']);
                    Route::get('/cidades/{estado}', [CidadesController::class, 'getCities']);
                });

                // Municípios — dados externos (IBGE SIDRA)
                Route::get('/municipios/{ibge_codigo}/dados-sidra', [MunicipioController::class, 'dadosSidra']);

                // Dashboard
                Route::prefix('dashboard')
                    ->middleware('check.feature:dashboard.enabled')
                    ->group(function () {
                        Route::get('/overview', [DashboardController::class, 'overview']);
                        Route::get('/management-overview', [DashboardController::class, 'managementOverview'])
                            ->middleware('check.feature:dashboard.overview');
                        Route::get('/cards', [DashboardController::class, 'cards']);
                        Route::get('/status-chart', [DashboardController::class, 'statusChart']);
                        Route::get('/cadastros-mensais', [DashboardController::class, 'cadastrosMensais']);
                        Route::get('/terrenos-responsavel', [DashboardController::class, 'terrenosPorResponsavel']);
                        Route::get('/top-cidades', [DashboardController::class, 'topCidades']);
                        Route::get('/vgv-anual', [DashboardController::class, 'vgvAnual'])
                            ->middleware('check.feature:dashboard.vgv');
                        Route::get('/unidades-fechadas-anual', [DashboardController::class, 'unidadesFechadasAnual'])
                            ->middleware('check.feature:dashboard.units_closed');
                        Route::get('/cadastros-mensais-responsavel', [DashboardController::class, 'cadastrosMensaisPorResponsavel']);
                        Route::get('/resumo', [DashboardController::class, 'resumoGeral']);
                        Route::get('/anos-disponiveis', [DashboardController::class, 'anosDisponiveis']);
                        Route::get('/area-opcao-detalhe', [DashboardController::class, 'areaOpcaoDetalhe']);
                    });

                // Mobile devices and inbox
                Route::prefix('mobile')->group(function () {
                    Route::middleware('check.feature:mobile.capture')->group(function () {
                        Route::post('/captures', [MobileCaptureController::class, 'store']);
                        Route::put('/captures/{clientId}', [MobileCaptureController::class, 'update'])
                            ->whereUuid('clientId');
                        Route::post('/captures/{clientId}/attachments', [MobileCaptureController::class, 'attachment'])
                            ->whereUuid('clientId');
                        Route::post('/captures/{clientId}/commit', [MobileCaptureController::class, 'commit'])
                            ->whereUuid('clientId');
                        Route::get('/captures/{clientId}/status', [MobileCaptureController::class, 'status'])
                            ->whereUuid('clientId');
                    });
                    Route::post('/devices', [MobileDeviceController::class, 'store']);
                    Route::delete('/devices/{installationId}', [MobileDeviceController::class, 'destroy']);
                    Route::get('/notifications', [MobileNotificationController::class, 'index']);
                    Route::get('/notifications/unread-count', [MobileNotificationController::class, 'unreadCount']);
                    Route::post('/notifications/read-all', [MobileNotificationController::class, 'readAll']);
                    Route::post('/notifications/{id}/read', [MobileNotificationController::class, 'read']);
                    Route::delete('/notifications/{id}', [MobileNotificationController::class, 'destroy']);
                });

                // Legalizações
                Route::middleware('check.feature:legalizations')->group(function () {
                    Route::get('/legalizacoes/eligible-terrenos', [LegalizacaoController::class, 'eligibleTerrenos']);
                    Route::middleware('check.feature:legalization.control_center')->group(function () {
                        Route::get('/legalizacoes/control-center', [LegalizacaoInsightController::class, 'controlCenter']);
                        Route::get('/legalizacoes/{id}/critical-path', [LegalizacaoInsightController::class, 'criticalPath'])
                            ->whereNumber('id');
                        Route::get('/legalizacoes/{id}/costs', [LegalizacaoInsightController::class, 'costs'])
                            ->whereNumber('id');
                    });
                    Route::post('/legalizacoes/{id}/sync-gantt', [LegalizacaoController::class, 'syncGantt']);
                    Route::post('/legalizacoes/{id}/recalcular-progresso', [LegalizacaoController::class, 'recalcularProgresso']);
                    Route::apiResource('legalizacoes', LegalizacaoController::class);

                    // Etapas de Legalização
                    Route::prefix('legalizacoes/{legalizacaoId}/etapas')->group(function () {
                        Route::get('/', [LegalizacaoEtapaController::class, 'index']);
                        Route::post('/', [LegalizacaoEtapaController::class, 'store']);
                        Route::get('/{id}', [LegalizacaoEtapaController::class, 'show']);
                        Route::put('/{id}', [LegalizacaoEtapaController::class, 'update']);
                        Route::delete('/{id}', [LegalizacaoEtapaController::class, 'destroy']);
                        Route::post('/reorder', [LegalizacaoEtapaController::class, 'reorder']);
                        Route::patch('/{id}/status', [LegalizacaoEtapaController::class, 'updateStatus']);
                    });
                });
            });
        });
    });

    // Tenant health check (requer autenticação para não vazar dados do tenant)
    Route::middleware(['auth:sanctum'])->get('/api/health', function (HealthCheckService $health) {
        $tenant = tenancy()->tenant;

        if (! $tenant instanceof CentralTenant) {
            return response()->json([
                'status' => 'error',
                'timestamp' => now()->toIso8601String(),
                'tenant' => null,
            ], 503);
        }

        $report = $health->check();
        $report['tenant'] = [
            'id' => $tenant->id,
            'name' => (string) $tenant->getAttribute('name'),
            'status' => (string) $tenant->getAttribute('status'),
        ];

        $statusCode = $report['status'] === 'down' ? 503 : 200;

        return response()->json($report, $statusCode);
    });
});
