<?php

use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Http\Controllers\PaymentController;
use Laravel\Cashier\Http\Middleware\VerifyRedirectUrl;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/registration', 'registration');

Route::get('/stripe/payment/{id}', [PaymentController::class, 'show'])
    ->middleware(VerifyRedirectUrl::class)
    ->name('cashier.payment');

// Alias de conveniência para a documentação da API (Scramble UI).
// Rota canônica: /docs/api (registrada automaticamente pelo dedoc/scramble).
Route::redirect('/docs', '/docs/api');
