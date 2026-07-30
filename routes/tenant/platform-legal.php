<?php

use App\Http\Controllers\Api\V1\CidadesController;
use App\Http\Controllers\Api\V1\MunicipioController;
use App\Http\Controllers\Api\V1\Tenant\DashboardController;
use App\Http\Controllers\Api\V1\Tenant\LegalizacaoController;
use App\Http\Controllers\Api\V1\Tenant\LegalizacaoEtapaController;
use App\Http\Controllers\Api\V1\Tenant\LegalizacaoInsightController;
use App\Http\Controllers\Api\V1\Tenant\MobileCaptureController;
use App\Http\Controllers\Api\V1\Tenant\MobileDeviceController;
use App\Http\Controllers\Api\V1\Tenant\MobileNotificationController;
use Illuminate\Support\Facades\Route;

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
        Route::get('/overview', [DashboardController::class, 'overview'])
            ->middleware('check.feature:dashboard.overview');
        Route::get('/management-overview', [DashboardController::class, 'managementOverview'])
            ->middleware('check.feature:dashboard.management');
        Route::get('/cards', [DashboardController::class, 'cards']);
        Route::get('/status-chart', [DashboardController::class, 'statusChart'])
            ->middleware('check.feature:dashboard.funnel');
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
            ->middleware('enforce.limits:storage_gb')
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
