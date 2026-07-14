<?php

use App\Http\Controllers\Api\V1\Tenant\AiController;
use App\Http\Controllers\Api\V1\Tenant\AiGeneratedReportController;
use App\Http\Controllers\Api\V1\Tenant\AiMonitorController;
use App\Http\Controllers\Api\V1\Tenant\AiPredictiveAnalysisController;
use App\Http\Controllers\Api\V1\Tenant\AiScoringController;
use App\Http\Controllers\Api\V1\Tenant\AiTaskController;
use App\Http\Controllers\Api\V1\Tenant\AiTerrenoReportController;
use App\Http\Controllers\Api\V1\Tenant\AiWorkflowController;
use App\Http\Controllers\Api\V1\Tenant\ContextualAiController;
use App\Http\Controllers\Api\V1\Tenant\PremissasViabilidadeController;
use App\Http\Controllers\Api\V1\Tenant\ViabilidadeController;
use App\Http\Controllers\Api\V1\Tenant\ViabilidadeScenarioController;
use Illuminate\Support\Facades\Route;

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
    Route::post('/ai/terrenos/{id}/relatorio-pdf/jobs', [AiTerrenoReportController::class, 'generateAsync'])
        ->middleware('ai.rate_limit', 'ai.budget')
        ->whereNumber('id')
        ->name('ai.terreno-reports.store');
    Route::get('/ai/terrenos/{id}/relatorio-pdf/jobs/{generation}', [AiTerrenoReportController::class, 'status'])
        ->whereNumber('id')
        ->whereNumber('generation')
        ->name('ai.terreno-reports.status');
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
