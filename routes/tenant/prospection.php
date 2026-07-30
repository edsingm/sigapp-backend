<?php

use App\Http\Controllers\Api\V1\Tenant\CorretoresExternosController;
use App\Http\Controllers\Api\V1\Tenant\DocumentIntelligenceController;
use App\Http\Controllers\Api\V1\Tenant\DocumentosController;
use App\Http\Controllers\Api\V1\Tenant\ProdutosController;
use App\Http\Controllers\Api\V1\Tenant\ProprietariosController;
use App\Http\Controllers\Api\V1\Tenant\RegionaisController;
use App\Http\Controllers\Api\V1\Tenant\ShortlistController;
use App\Http\Controllers\Api\V1\Tenant\TerrenoController;
use App\Http\Controllers\Api\V1\Tenant\TerrenoImportController;
use App\Http\Controllers\Api\V1\Tenant\TerrenoPolygonImportController;
use App\Http\Controllers\Api\V1\Tenant\TerrenoProdutosController;
use App\Http\Controllers\Api\V1\Tenant\TerrenosExportController;
use App\Http\Controllers\Api\V1\Tenant\TerrenoWorkflowController;
use App\Http\Controllers\Api\V1\Tenant\TimelineController;
use App\Http\Controllers\Api\V1\Tenant\ViabilidadeController;
use Illuminate\Support\Facades\Route;

// Terrenos (with plan limit enforcement)
Route::middleware(['check.feature:prospection', 'enforce.limits:terrenos'])->group(function () {
    Route::post('/terrenos', [TerrenoController::class, 'store'])
        ->middleware('permission.gate:prospection,terrains');
});
// Rotas específicas devem vir ANTES do apiResource
Route::middleware('check.feature:prospection')->group(function () {
    Route::prefix('terrenos/imports')->middleware('permission.gate:prospection,terrains')->group(function () {
        Route::get('/template', [TerrenoImportController::class, 'template'])
            ->name('tenant.terreno-imports.template');
        Route::post('/', [TerrenoImportController::class, 'store'])
            ->middleware(['throttle:terrain-imports', 'enforce.limits:storage_gb'])
            ->name('tenant.terreno-imports.store');
        Route::get('/{import}', [TerrenoImportController::class, 'show'])
            ->whereNumber('import')
            ->name('tenant.terreno-imports.show');
        Route::get('/{import}/rows', [TerrenoImportController::class, 'rows'])
            ->whereNumber('import')
            ->name('tenant.terreno-imports.rows');
        Route::post('/{import}/confirm', [TerrenoImportController::class, 'confirm'])
            ->whereNumber('import')
            ->name('tenant.terreno-imports.confirm');
        Route::get('/{import}/errors', [TerrenoImportController::class, 'errors'])
            ->whereNumber('import')
            ->name('tenant.terreno-imports.errors');
    });
    Route::post('/terrenos/polygon-imports', [TerrenoPolygonImportController::class, 'store'])
        ->middleware([
            'permission.gate:prospection,terrains',
            'throttle:terrain-imports',
            'enforce.limits:storage_gb',
        ])
        ->name('tenant.terreno-polygon-imports.store');
    Route::get('/terrenos/polygon-imports/{import}', [TerrenoPolygonImportController::class, 'show'])
        ->whereNumber('import')
        ->middleware('permission.gate:prospection,terrains')
        ->name('tenant.terreno-polygon-imports.show');
    Route::get('/terrenos/polygons', [TerrenoPolygonImportController::class, 'index'])
        ->middleware('permission.gate:prospection,maps')
        ->name('tenant.terreno-polygons.index');
    Route::post('/terrenos/polygons/{polygon}/link', [TerrenoPolygonImportController::class, 'link'])
        ->whereNumber('polygon')
        ->middleware('permission.gate:prospection,terrains')
        ->name('tenant.terreno-polygons.link');
    Route::delete('/terrenos/polygons/{polygon}', [TerrenoPolygonImportController::class, 'destroy'])
        ->whereNumber('polygon')
        ->middleware('permission.gate:prospection,terrains')
        ->name('tenant.terreno-polygons.destroy');
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
        Route::post('/{documento}/versions', [DocumentIntelligenceController::class, 'storeVersion'])
            ->whereNumber('documento')
            ->middleware('enforce.limits:storage_gb');
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
