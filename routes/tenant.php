<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Tenant\TenantController;
use App\Http\Controllers\Api\V1\Tenant\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Api\V1\Tenant\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Api\V1\Tenant\Admin\UserManagementController as AdminUserManagementController;
use App\Http\Controllers\Api\V1\Tenant\UserController;
use App\Http\Controllers\Api\V1\Tenant\TerrenoController;
use App\Http\Controllers\Api\V1\Tenant\DocumentosController;
use App\Http\Controllers\Api\V1\Tenant\CorretoresExternosController;
use App\Http\Controllers\Api\V1\Tenant\RegionaisController;
use App\Http\Controllers\Api\V1\Tenant\ProdutosController;
use App\Http\Controllers\Api\V1\Tenant\ProprietariosController;
use App\Http\Controllers\Api\V1\Tenant\TerrenoProdutosController;
use App\Http\Controllers\Api\V1\Tenant\TerrenosExportController;
use App\Http\Controllers\Api\V1\Tenant\ViabilidadeController;
use App\Http\Controllers\Api\V1\Tenant\DashboardController;
use App\Http\Controllers\Api\V1\Tenant\LegalizacaoController;
use App\Http\Controllers\Api\V1\Tenant\LegalizacaoEtapaController;
use App\Http\Controllers\Api\V1\Tenant\MobileDeviceController;
use App\Http\Controllers\Api\V1\Tenant\MobileNotificationController;
use App\Http\Controllers\Api\V1\Tenant\CommitteeController;
use App\Http\Controllers\Api\V1\Tenant\ContractController;
use App\Http\Controllers\Api\V1\Tenant\NegotiationController;
use App\Http\Controllers\Api\V1\Tenant\ProjetoController;
use App\Http\Controllers\Api\V1\Tenant\TerrenoWorkflowController;
use App\Http\Controllers\Api\V1\Tenant\AiController;
use App\Http\Controllers\Api\V1\CidadesController;
use App\Http\Controllers\Api\V1\LanguageController;
use App\Http\Controllers\Api\V1\Tenant\Common\ModulesController;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SetUserLocale;
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
        Route::middleware('throttle:api-public')->group(function () {

            // Auth - Login for tenant
            Route::post('/auth/login', [AuthController::class, 'login']);
            Route::post('/auth/exchange-ticket', [AuthController::class, 'exchangeTicket'])
                ->middleware('throttle:transfer-ticket');
            Route::post('/auth/password/forgot', [AuthController::class, 'forgotPassword'])
                ->middleware('throttle:password-reset-request');
            Route::post('/auth/password/reset', [AuthController::class, 'resetPassword'])
                ->middleware('throttle:password-reset-submit');
        });
        // Authenticated tenant routes (always accessible after login)
        Route::middleware([
            'auth:sanctum',
            'throttle:api-auth',
            SetUserLocale::class,
        ])->group(function () {

            // Auth
            Route::post('/auth/logout', [AuthController::class, 'logout']);
            Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
            Route::post('/auth/refresh', [AuthController::class, 'refresh']);
            Route::get('/auth/me', [AuthController::class, 'me']);
            Route::put('/auth/me', [AuthController::class, 'updateMe']);

            // Locale
            Route::put('/locale', [LanguageController::class, 'set']);

            // Bootstrap: modules, plan and user RBAC for navbar/feature gating
            Route::get('/start', [ModulesController::class, 'index']);

            Route::get('/tenant/subscription', [TenantController::class, 'subscription']);
            Route::post('/tenant/billing-portal', [TenantController::class, 'billingPortal']);

            Route::middleware(CheckSubscriptionStatus::class)->group(function () {

                // Tenant info
                Route::get('/tenant', [TenantController::class, 'show']);
                Route::get('/tenant/usage', [TenantController::class, 'usage']);

                // Users (select inputs for tenant forms)
                Route::get('/users/for-select', [UserController::class, 'usersForSelect']);

                // Tenant admin (users, roles and permissions)
                Route::prefix('tenant-admin')
                    ->middleware('tenant.admin')
                    ->as('tenant-admin.')
                    ->group(function () {
                        Route::post('users', [AdminUserManagementController::class, 'store'])
                            ->middleware('enforce.limits:users')
                            ->name('tenant-admin.users.store');
                        Route::apiResource('users', AdminUserManagementController::class)->except(['store']);
                        Route::put('users/{id}/module-permissions', [AdminUserManagementController::class, 'updateModulePermissions'])
                            ->name('tenant-admin.users.module-permissions');
                        Route::get('roles/select', [AdminRoleController::class, 'forSelect'])
                            ->name('tenant-admin.roles.select');
                        Route::apiResource('roles', AdminRoleController::class)->only(['index', 'show']);
                        Route::apiResource('permissions', AdminPermissionController::class)->only(['index', 'show']);
                    });

                // Terrenos (with plan limit enforcement)
                Route::middleware(['check.feature:prospection', 'enforce.limits:terrenos'])->group(function () {
                    Route::post('/terrenos', [TerrenoController::class, 'store'])
                        ->middleware('permission.gate:prospection,terrains');
                });
                // Rotas específicas devem vir ANTES do apiResource
                Route::middleware('check.feature:prospection')->group(function () {
                    Route::get('/terrenos/filter', [TerrenoController::class, 'filter']);
                    Route::get('/terrenos/select', [TerrenoController::class, 'forSelect']);
                    Route::get('/terrenos/{id}/informacoes', [TerrenoController::class, 'getInformacoes'])
                        ->middleware('permission.gate:prospection,terrains');
                    Route::post('/terrenos/{id}/informacoes', [TerrenoController::class, 'storeInfo']);
                    Route::put('/terrenos/informacoes/{infoId}', [TerrenoController::class, 'updateInfo']);
                    Route::delete('/terrenos/informacoes/{infoId}', [TerrenoController::class, 'destroyInfo']);
                    Route::get('/terrenos/{id}/workflow', [TerrenoWorkflowController::class, 'show']);
                    Route::post('/terrenos/{id}/workflow', [TerrenoWorkflowController::class, 'update']);
                    Route::put('/terrenos/{id}/qualificacao', [TerrenoWorkflowController::class, 'updateQualification']);
                    Route::apiResource('terrenos', TerrenoController::class)->except(['store']);
                });

                // Documentos
                Route::prefix('documentos')->group(function () {
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
                    Route::get('/regionais/select', [RegionaisController::class, 'regionaisForSelect']);
                    Route::apiResource('regionais', RegionaisController::class);
                });

                // Produtos
                Route::middleware('check.feature:product_settings')->group(function () {
                    Route::get('/produtos/select', [ProdutosController::class, 'produtosForSelect']);
                    Route::post('/produtos', [ProdutosController::class, 'store'])
                        ->middleware('enforce.limits:products');
                    Route::apiResource('produtos', ProdutosController::class)->except(['store']);
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
                    Route::post('/viabilidades/{id}/ativar', [ViabilidadeController::class, 'ativar']);
                    Route::post('/viabilidades/{id}/duplicate', [ViabilidadeController::class, 'duplicate']);
                    Route::post('/viabilidades/{id}/gerar-dre', [ViabilidadeController::class, 'gerarDre'])
                        ->middleware('check.feature:viabilities.dre');
                    Route::post('/viabilidades/{id}/recalcular', [ViabilidadeController::class, 'recalcular']);
                    Route::post('/viabilidades/{id}/restore', [ViabilidadeController::class, 'restore']);
                    Route::apiResource('viabilidades', ViabilidadeController::class);
                });

                // AI
                Route::middleware('check.feature:ai')->group(function () {
                    Route::get('/ai/conversations', [AiController::class, 'conversations']);
                    Route::get('/ai/conversations/{id}/messages', [AiController::class, 'conversationMessages']);
                    Route::post('/ai/sig-ai', [AiController::class, 'chat']);
                });

                // Projetos
                Route::middleware('check.feature:projects_room')->group(function () {
                    Route::get('/projetos/eligible-terrenos', [ProjetoController::class, 'eligibleTerrenos']);
                    Route::post('/projetos/{id}/marcar-pronto-registro', [ProjetoController::class, 'markReady']);
                    Route::post('/projetos/{id}/cancelar', [ProjetoController::class, 'cancel']);
                    Route::apiResource('projetos', ProjetoController::class)->only(['index', 'store', 'show', 'update']);
                });

                // Comitê
                Route::middleware('check.feature:committee')->group(function () {
                    Route::get('/comite', [CommitteeController::class, 'index']);
                    Route::post('/comite', [CommitteeController::class, 'store']);
                    Route::get('/comite/{id}', [CommitteeController::class, 'show']);
                    Route::post('/comite/{id}/department-reviews', [CommitteeController::class, 'upsertDepartmentReview']);
                    Route::post('/comite/{id}/decision', [CommitteeController::class, 'finalize']);
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
                });

                // Cidades e Estados
                Route::middleware('check.feature:territorial_base')->group(function () {
                    Route::get('/cidades/estados', [CidadesController::class, 'index']);
                    Route::get('/cidades/buscar', [CidadesController::class, 'buscar']);
                    Route::get('/cidades/dados', [CidadesController::class, 'dados']);
                    Route::get('/cidades/{estado}', [CidadesController::class, 'getCities']);
                });

                // Dashboard
                Route::prefix('dashboard')
                    ->middleware('check.feature:dashboard.enabled')
                    ->group(function () {
                        Route::get('/overview', [DashboardController::class, 'overview']);
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
                    Route::post('/devices', [MobileDeviceController::class, 'store']);
                    Route::delete('/devices/{installationId}', [MobileDeviceController::class, 'destroy']);
                    Route::get('/notifications', [MobileNotificationController::class, 'index']);
                    Route::post('/notifications/{id}/read', [MobileNotificationController::class, 'read']);
                });

                // Legalizações
                Route::middleware('check.feature:legalizations')->group(function () {
                    Route::get('/legalizacoes/eligible-terrenos', [LegalizacaoController::class, 'eligibleTerrenos']);
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
    Route::middleware(['auth:sanctum'])->get('/api/health', function () {
        $tenant = tenancy()->tenant;

        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status,
            ],
        ]);
    });
});
