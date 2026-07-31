<?php

use App\Http\Controllers\Api\V1\Tenant\CommitteeAiDossierController;
use App\Http\Controllers\Api\V1\Tenant\CommitteeController;
use App\Http\Controllers\Api\V1\Tenant\CommitteeMeetingController;
use App\Http\Controllers\Api\V1\Tenant\ProjetoController;
use App\Http\Controllers\Api\V1\Tenant\ProjetoPlanningController;
use Illuminate\Support\Facades\Route;

// Projetos
Route::middleware('check.feature:projects.enabled')->group(function () {
    Route::get('/projetos/eligible-terrenos', [ProjetoController::class, 'eligibleTerrenos']);
    Route::post('/projetos/{id}/marcar-pronto-registro', [ProjetoController::class, 'markReady']);
    Route::post('/projetos/{id}/cancelar', [ProjetoController::class, 'cancel']);
    Route::apiResource('projetos', ProjetoController::class)->only(['index', 'store', 'show', 'update']);

    Route::middleware('check.feature:projects.planning')->group(function () {
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
    Route::get('/comite/{id}/ai-dossier/export-pdf', [CommitteeAiDossierController::class, 'exportPdf'])
        ->middleware('check.feature:exports.pdf')
        ->whereNumber('id');
    Route::post('/comite/{id}/ai-dossier/regenerate', [CommitteeAiDossierController::class, 'regenerate'])
        ->middleware('ai.rate_limit', 'ai.budget')
        ->whereNumber('id');
    Route::post('/comite/{id}/department-reviews', [CommitteeController::class, 'upsertDepartmentReview'])->whereNumber('id');
    Route::post('/comite/{id}/decision', [CommitteeController::class, 'finalize'])->whereNumber('id');

    Route::middleware('check.feature:committee.meeting')->group(function () {
        Route::get('/comite/sessions', [CommitteeMeetingController::class, 'index']);
        Route::post('/comite/sessions', [CommitteeMeetingController::class, 'store']);
        Route::get('/comite/sessions/{session}', [CommitteeMeetingController::class, 'show'])
            ->whereNumber('session');
        Route::put('/comite/sessions/{session}', [CommitteeMeetingController::class, 'update'])
            ->whereNumber('session');
        Route::post('/comite/sessions/{session}/start', [CommitteeMeetingController::class, 'start'])
            ->middleware('check.feature:committee.meeting_mode')
            ->whereNumber('session');
        Route::post('/comite/sessions/{session}/close', [CommitteeMeetingController::class, 'finish'])
            ->middleware('check.feature:committee.meeting_mode')
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
            ->middleware('check.feature:committee.meeting_mode')
            ->whereNumber('session');
        Route::post('/comite/sessions/{session}/minutes', [CommitteeMeetingController::class, 'saveMinutes'])
            ->middleware('check.feature:committee.meeting_mode')
            ->whereNumber('session');
        Route::put('/comite/sessions/{session}/minutes', [CommitteeMeetingController::class, 'saveMinutes'])
            ->middleware('check.feature:committee.meeting_mode')
            ->whereNumber('session');
        Route::post('/comite/sessions/{session}/minutes/approve', [CommitteeMeetingController::class, 'approveMinutes'])
            ->middleware('check.feature:committee.meeting_mode')
            ->whereNumber('session');
    });
});
