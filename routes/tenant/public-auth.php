<?php

use App\Http\Controllers\Api\V1\LegalDocumentController;
use App\Http\Controllers\Api\V1\TenantAuthController;
use App\Http\Controllers\Api\V1\TenantPasswordResetController;
use Illuminate\Support\Facades\Route;

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

    Route::get('/legal/documents', [LegalDocumentController::class, 'index'])
        ->name('tenant.legal.documents.index');
});
