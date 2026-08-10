<?php

use App\Http\Controllers\Api\V1\LanguageController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\Tenant\BillingHistoryController;
use App\Http\Controllers\Api\V1\Tenant\Common\ModulesController;
use App\Http\Controllers\Api\V1\Tenant\CouponController as TenantCouponController;
use App\Http\Controllers\Api\V1\Tenant\DunningController;
use App\Http\Controllers\Api\V1\Tenant\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Tenant\PlanSwapController;
use App\Http\Controllers\Api\V1\Tenant\PlatformAnnouncementController;
use App\Http\Controllers\Api\V1\Tenant\TenantAddonController;
use App\Http\Controllers\Api\V1\Tenant\TenantController;
use App\Http\Controllers\Api\V1\TenantAuthController;
use Illuminate\Support\Facades\Route;

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

Route::middleware('tenant.admin')->group(function () {
    Route::get('/tenant/billing-profile', [TenantController::class, 'billingProfile']);
    Route::put('/tenant/billing-profile', [TenantController::class, 'updateBillingProfile']);
});

// Anúncios da plataforma (banner no app do tenant)
Route::get('/platform-announcements/active', [PlatformAnnouncementController::class, 'active']);
Route::post('/platform-announcements/{announcement}/dismiss', [PlatformAnnouncementController::class, 'dismiss'])
    ->whereNumber('announcement');

Route::get('/tenant/subscription', [TenantController::class, 'subscription'])
    ->middleware('tenant.admin');
Route::post('/tenant/billing-portal', [TenantController::class, 'billingPortal'])
    ->middleware('tenant.admin');

// Catálogo de planos acessível no domínio do tenant (a rota pública
// /plans é central-only). Usado pela tela de faturamento para montar
// as opções de upgrade/downgrade. Admin-only.
Route::get('/tenant/plans', [PlanController::class, 'index'])
    ->middleware('tenant.admin');

// Add-ons Stripe — somente o admin do tenant pode contratar e alterar quantidade.
Route::middleware('tenant.admin')->group(function () {
    Route::get('/tenant/addons', [TenantAddonController::class, 'index']);
    Route::get('/tenant/addons/mine', [TenantAddonController::class, 'mine']);
    Route::post('/tenant/addons/purchase', [TenantAddonController::class, 'purchase']);
    Route::patch('/tenant/addons/{addon}', [TenantAddonController::class, 'update'])
        ->whereNumber('addon');
    Route::post('/tenant/addons/{addon}/cancel', [TenantAddonController::class, 'cancel'])
        ->whereNumber('addon');
});

// Billing — troca de plano, histórico e atualização de método de pagamento
// Acessíveis mesmo com assinatura suspensa (tenant pode reativar/atualizar sem bloqueio).
// Compra/alteração de add-ons é bloqueada no service quando o tenant não está active.
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

// Histórico de faturas — regularização com tenant suspenso (fora de CheckSubscriptionStatus)
Route::prefix('tenant/billing')->group(function () {
    Route::get('/history', [BillingHistoryController::class, 'index']);
    Route::get('/invoices/{invoiceId}', [BillingHistoryController::class, 'show']);
    Route::get('/invoices/{invoiceId}/pdf', [BillingHistoryController::class, 'downloadPdf']);
});
