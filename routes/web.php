<?php

use Laravel\Cashier\Http\Controllers\PaymentController;
use Laravel\Cashier\Http\Middleware\VerifyRedirectUrl;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/registration', 'registration');

Route::get('/stripe/payment/{id}', [PaymentController::class, 'show'])
    ->middleware(VerifyRedirectUrl::class)
    ->name('cashier.payment');
