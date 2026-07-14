<?php

use App\Http\Controllers\Api\V1\Tenant\ContractController;
use App\Http\Controllers\Api\V1\Tenant\NegotiationController;
use App\Http\Controllers\Api\V1\Tenant\NegotiationDealRoomController;
use Illuminate\Support\Facades\Route;

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
