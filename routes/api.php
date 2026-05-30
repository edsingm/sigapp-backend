<?php

use App\Http\Controllers\Api\V1\Admin\AclController;
use App\Http\Controllers\Api\V1\Admin\AuditController;
use App\Http\Controllers\Api\V1\Admin\CouponController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\EntitlementController;
use App\Http\Controllers\Api\V1\Admin\PlanAdminController;
use App\Http\Controllers\Api\V1\Admin\PostController;
use App\Http\Controllers\Api\V1\Admin\TenantController;
use App\Http\Controllers\Api\V1\Admin\TenantPlanController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\BlogController;
use App\Http\Controllers\Api\V1\CentralAuthController;
use App\Http\Controllers\Api\V1\LanguageController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\PublicTenantController;
use App\Http\Controllers\Api\V1\SignupController;
use App\Http\Controllers\Api\V1\TenantStatusController;
use App\Http\Controllers\Api\V1\TenantAuthController;
use App\Http\Controllers\Api\V1\TenantPasswordResetController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SetUserLocale;
use App\Services\ApiResponseService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Central Application
|--------------------------------------------------------------------------
|
| Routes for the central application (without tenant context).
| These are accessible at: /api/v1/...
|
*/

// Configure rate limiters
RateLimiter::for('api-public', function (Request $request) {
    return Limit::perMinute(60)->by($request->ip());
});

RateLimiter::for('api-auth', function (Request $request) {
    $user = $request->user();
    $tenantId = tenancy()->initialized ? (string) tenant('id') : null;
    $key = $tenantId
        ? ($user ? "tenant:{$tenantId}:user:{$user->id}" : "tenant:{$tenantId}:ip:{$request->ip()}")
        : ($user ? "central:user:{$user->id}" : "central:ip:{$request->ip()}");

    return Limit::perMinute(1000)
        ->by($key)
        ->response(function () {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TOO_MANY_REQUESTS',
                    'message' => 'Muitas requisições. Tente novamente em 1 minuto.',
                ],
            ], 429);
        });
});

RateLimiter::for('central-login', function (Request $request) {
    $email = strtolower(trim((string) $request->input('email', '')));

    return Limit::perMinute(5)
        ->by('central-login:'.$request->ip().':'.sha1($email))
        ->response(fn () => ApiResponseService::tooManyRequests('Muitas tentativas de login. Tente novamente em 1 minuto.'));
});

RateLimiter::for('central-login-select', function (Request $request) {
    return Limit::perMinute(10)
        ->by('central-login-select:'.$request->ip())
        ->response(fn () => ApiResponseService::tooManyRequests());
});

RateLimiter::for('admin-login', function (Request $request) {
    $email = strtolower(trim((string) $request->input('email', '')));

    return Limit::perMinute(5)
        ->by('admin-login:'.$request->ip().':'.sha1($email))
        ->response(fn () => ApiResponseService::tooManyRequests('Muitas tentativas de login de administrador. Tente novamente em 1 minuto.'));
});

RateLimiter::for('transfer-ticket', function (Request $request) {
    $tenantKey = tenancy()->initialized ? (string) tenant('id') : 'no-tenant';

    return Limit::perMinute(15)
        ->by('transfer-ticket:'.$tenantKey.':'.$request->ip())
        ->response(fn () => ApiResponseService::tooManyRequests());
});

RateLimiter::for('password-reset-request', function (Request $request) {
    $email = strtolower(trim((string) $request->input('email', '')));

    return Limit::perMinute(5)
        ->by('password-reset-request:'.$request->ip().':'.sha1($email))
        ->response(fn () => ApiResponseService::tooManyRequests('Muitas solicitações de redefinição. Tente novamente em 1 minuto.'));
});

RateLimiter::for('password-reset-submit', function (Request $request) {
    return Limit::perMinute(10)
        ->by('password-reset-submit:'.$request->ip())
        ->response(fn () => ApiResponseService::tooManyRequests('Muitas tentativas de redefinição. Tente novamente em 1 minuto.'));
});

RateLimiter::for('signup-status', function (Request $request) {
    $sessionParameter = $request->route('sessionId', '');
    $sessionId = is_scalar($sessionParameter) ? (string) $sessionParameter : '';

    return Limit::perMinute(30)
        ->by('signup-status:'.$request->ip().':'.sha1($sessionId))
        ->response(fn () => ApiResponseService::tooManyRequests('Muitas consultas de status. Aguarde 1 minuto.'));
});

RateLimiter::for('viabilidade-approval', function (Request $request) {
    $user = $request->user();
    $tenantId = tenancy()->initialized ? (string) tenant('id') : 'no-tenant';
    $key = $user
        ? "viabilidade-approval:{$tenantId}:user:{$user->id}"
        : "viabilidade-approval:{$tenantId}:ip:{$request->ip()}";

    return Limit::perMinute(10)
        ->by($key)
        ->response(fn () => ApiResponseService::tooManyRequests('Muitas ações de aprovação em curto período. Aguarde 1 minuto.'));
});

// All API routes use JSON
Route::middleware([ForceJsonResponse::class])->group(function () {

    // API v1 prefix
    Route::prefix('v1')->group(function () {

        // Universal routes (accessible on any domain)
        Route::middleware('throttle:api-public')->group(function () {
            Route::get('/tenant/subdomain-availability/{subdomain}', [PublicTenantController::class, 'subdomainAvailability']);
        });

        // Central Only Routes
        foreach (config('tenancy.identification.central_domains') as $domain) {
            Route::domain($domain)->group(function () {

                // Public routes (no authentication)
                Route::middleware(['central.context', 'throttle:api-public'])->group(function () {

                    // Plans
                    Route::get('/plans', [PlanController::class, 'index']);
                    Route::get('/plans/{slug}', [PlanController::class, 'show']);

                    // Signup
                    Route::post('/signup', [SignupController::class, 'store']);
                    Route::get('/signup/{sessionId}/status', [SignupController::class, 'status'])
                        ->middleware('throttle:signup-status');

                    // Stripe Webhook (no CSRF, no throttle)
                    Route::post('/webhook/stripe', [WebhookController::class, 'handleWebhook'])
                        ->withoutMiddleware(['throttle:api-public']);

                    // Auth - central broker flow
                    Route::post('/auth/login', [CentralAuthController::class, 'login'])
                        ->middleware('throttle:central-login');
                    Route::post('/auth/select-tenant', [CentralAuthController::class, 'selectTenant'])
                        ->middleware('throttle:central-login-select');
                    Route::post('/auth/password/forgot', [TenantPasswordResetController::class, 'forgotPassword'])
                        ->middleware('throttle:password-reset-request');
                    Route::post('/auth/password/reset', [TenantPasswordResetController::class, 'resetPassword'])
                        ->middleware('throttle:password-reset-submit');

                    // Blog (public)
                    Route::get('/blog', [BlogController::class, 'index']);
                    Route::get('/blog/categories', [BlogController::class, 'categories']);
                    Route::get('/blog/{slug}', [BlogController::class, 'show']);
                });

                // Authenticated routes (central app)
                Route::middleware(['central.context', 'auth:sanctum', 'auth.central', 'central.admin', 'throttle:api-auth', SetUserLocale::class])->group(function () {

                    // Locale
                    Route::put('/locale', [LanguageController::class, 'set']);

                    // Auth
                    Route::post('/auth/logout', [TenantAuthController::class, 'logout']);
                    Route::post('/auth/logout-all', [TenantAuthController::class, 'logoutAll']);
                    Route::post('/auth/refresh', [TenantAuthController::class, 'refresh']);
                    Route::get('/auth/me', [TenantAuthController::class, 'me']);
                });

                Route::middleware(['central.context', 'auth:sanctum', 'auth.central', 'central.admin', 'throttle:api-auth'])->group(function () {
                    Route::get('/tenant-status', [TenantStatusController::class, 'index']);

                    // Admin Routes
                    Route::prefix('admin')->name('admin.')->group(function () {
                        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
                        Route::apiResource('posts', PostController::class);

                        // Tenants
                        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
                        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
                        Route::post('/tenants/{tenant}/activate', [TenantController::class, 'activate'])->name('tenants.activate');
                        Route::post('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');

                        // Tenant Plan Management
                        Route::post('/tenants/{id}/plan', [TenantPlanController::class, 'assignPlan'])->name('tenants.plan.assign');
                        Route::put('/tenants/{id}/plan/upgrade', [TenantPlanController::class, 'upgradePlan'])->name('tenants.plan.upgrade');
                        Route::put('/tenants/{id}/plan/downgrade', [TenantPlanController::class, 'downgradePlan'])->name('tenants.plan.downgrade');

                        // Tenant Extra Entitlements
                        Route::get('/tenants/{id}/entitlements', [TenantPlanController::class, 'extraEntitlements'])->name('tenants.entitlements.index');
                        Route::post('/tenants/{id}/entitlements', [TenantPlanController::class, 'addExtraEntitlement'])->name('tenants.entitlements.store');
                        Route::put('/tenants/{id}/entitlements/{entitlementId}', [TenantPlanController::class, 'updateExtraEntitlement'])->name('tenants.entitlements.update');
                        Route::delete('/tenants/{id}/entitlements/{entitlementId}', [TenantPlanController::class, 'removeExtraEntitlement'])->name('tenants.entitlements.destroy');

                        // Users
                        Route::apiResource('users', UserController::class);

                        // Audit Logs
                        Route::get('/audit-logs', [AuditController::class, 'index']);

                        // ACL Catalog / Plan Role Matrix (read-only, foundation for UI de gestão)
                        Route::get('/acl/catalog', [AclController::class, 'catalog']);
                        Route::get('/acl/plans/{planId}/role-matrix', [AclController::class, 'planRoleMatrix']);

                        // Plans — CRUD admin
                        Route::apiResource('plans', PlanAdminController::class);
                        Route::put('/plans/{plan}/entitlements', [PlanAdminController::class, 'syncEntitlements'])
                            ->name('admin.plans.entitlements.sync');

                        // Entitlements — CRUD admin
                        Route::apiResource('entitlements', EntitlementController::class);

                        // Coupons — CRUD admin
                        Route::apiResource('coupons', CouponController::class)->except(['create', 'edit']);
                    });
                });

                // Public Admin Login
                Route::post('/admin/login', [AdminController::class, 'login'])
                    ->middleware('throttle:admin-login');
            });
        }
        // End of API v1 prefix
    });
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'version' => config('app.version', '1.0.0'),
    ]);
});
