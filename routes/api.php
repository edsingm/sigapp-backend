<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\SignupController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Middleware\ForceJsonResponse;
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
| These are accessible at: https://sigpro.com.br/api/v1/...
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
        ->by('central-login:' . $request->ip() . ':' . sha1($email))
        ->response(fn() => \App\Services\ApiResponseService::tooManyRequests('Muitas tentativas de login. Tente novamente em 1 minuto.'));
});

RateLimiter::for('central-login-select', function (Request $request) {
    return Limit::perMinute(10)
        ->by('central-login-select:' . $request->ip())
        ->response(fn() => \App\Services\ApiResponseService::tooManyRequests());
});

RateLimiter::for('admin-login', function (Request $request) {
    $email = strtolower(trim((string) $request->input('email', '')));

    return Limit::perMinute(5)
        ->by('admin-login:' . $request->ip() . ':' . sha1($email))
        ->response(fn() => \App\Services\ApiResponseService::tooManyRequests('Muitas tentativas de login de administrador. Tente novamente em 1 minuto.'));
});

RateLimiter::for('transfer-ticket', function (Request $request) {
    $tenantKey = tenancy()->initialized ? (string) tenant('id') : 'no-tenant';

    return Limit::perMinute(15)
        ->by('transfer-ticket:' . $tenantKey . ':' . $request->ip())
        ->response(fn() => \App\Services\ApiResponseService::tooManyRequests());
});

RateLimiter::for('password-reset-request', function (Request $request) {
    $email = strtolower(trim((string) $request->input('email', '')));

    return Limit::perMinute(5)
        ->by('password-reset-request:' . $request->ip() . ':' . sha1($email))
        ->response(fn() => \App\Services\ApiResponseService::tooManyRequests('Muitas solicitações de redefinição. Tente novamente em 1 minuto.'));
});

RateLimiter::for('password-reset-submit', function (Request $request) {
    return Limit::perMinute(10)
        ->by('password-reset-submit:' . $request->ip())
        ->response(fn() => \App\Services\ApiResponseService::tooManyRequests('Muitas tentativas de redefinição. Tente novamente em 1 minuto.'));
});

// All API routes use JSON
Route::middleware([ForceJsonResponse::class])->group(function () {

    // API v1 prefix
    Route::prefix('v1')->group(function () {

        // Universal routes (accessible on any domain)
        Route::middleware('throttle:api-public')->group(function () {
            Route::get('/tenant/subdomain-availability/{subdomain}', [\App\Http\Controllers\Api\V1\PublicTenantController::class, 'subdomainAvailability']);
            Route::get('/tenant/by-subdomain/{subdomain}', [\App\Http\Controllers\Api\V1\PublicTenantController::class, 'bySubdomain']);
            Route::get('/tenant/discover', [\App\Http\Controllers\Api\V1\PublicTenantController::class, 'discover']);
        });

        // Central Only Routes
        foreach (config('tenancy.identification.central_domains') as $domain) {
            Route::domain($domain)->group(function () {

                // Public routes (no authentication)
                Route::middleware('throttle:api-public')->group(function () {

                    // Plans
                    Route::get('/plans', [PlanController::class, 'index']);
                    Route::get('/plans/{slug}', [PlanController::class, 'show']);

                    // Signup
                    Route::post('/signup', [SignupController::class, 'store']);
                    Route::get('/signup/{sessionId}/status', [SignupController::class, 'status']);

                    // Stripe Webhook (no CSRF, no throttle)
                    Route::post('/webhook/stripe', [WebhookController::class, 'handleWebhook'])
                        ->withoutMiddleware(['throttle:api-public']);

                    // Auth - Login (public)
                    Route::post('/auth/login', [AuthController::class, 'login']);
                    Route::post('/auth/login-tenant', [AuthController::class, 'loginWithTenant']);
                    Route::post('/auth/central-login', [AuthController::class, 'centralLogin'])
                        ->middleware('throttle:central-login');
                    Route::post('/auth/central-login/select-tenant', [AuthController::class, 'selectCentralLoginTenant'])
                        ->middleware('throttle:central-login-select');
                    Route::post('/auth/password/forgot', [AuthController::class, 'forgotPassword'])
                        ->middleware('throttle:password-reset-request');
                    Route::post('/auth/password/reset', [AuthController::class, 'resetPassword'])
                        ->middleware('throttle:password-reset-submit');

                    // Blog (public)
                    Route::get('/blog', [\App\Http\Controllers\Api\V1\BlogController::class, 'index']);
                    Route::get('/blog/categories', [\App\Http\Controllers\Api\V1\BlogController::class, 'categories']);
                    Route::get('/blog/{slug}', [\App\Http\Controllers\Api\V1\BlogController::class, 'show']);

                });

                // Authenticated routes (central app)
                Route::middleware(['auth:sanctum', 'user.admin', 'throttle:api-auth'])->group(function () {

                    // Auth
                    Route::post('/auth/logout', [AuthController::class, 'logout']);
                    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
                    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
                    Route::get('/auth/me', [AuthController::class, 'me']);

                });

                Route::middleware(['auth:sanctum', 'user.admin', 'throttle:api-auth'])->group(function () {

                    // Admin Routes
                    Route::prefix('admin')->group(function () {
                        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\Admin\DashboardController::class, 'index']);
                        Route::apiResource('posts', \App\Http\Controllers\Api\V1\Admin\PostController::class);

                        // Tenants
                        Route::get('/tenants', [\App\Http\Controllers\Api\V1\Admin\TenantController::class, 'index']);
                        Route::get('/tenants/{id}', [\App\Http\Controllers\Api\V1\Admin\TenantController::class, 'show']);
                        Route::post('/tenants/{id}/activate', [\App\Http\Controllers\Api\V1\Admin\TenantController::class, 'activate']);
                        Route::post('/tenants/{id}/suspend', [\App\Http\Controllers\Api\V1\Admin\TenantController::class, 'suspend']);

                        // Users
                        Route::apiResource('users', \App\Http\Controllers\Api\V1\Admin\UserController::class);

                        // Audit Logs
                        Route::get('/audit-logs', [\App\Http\Controllers\Api\V1\Admin\AuditController::class, 'index']);

                        // ACL Catalog / Plan Role Matrix (read-only, foundation for UI de gestão)
                        Route::get('/acl/catalog', [\App\Http\Controllers\Api\V1\Admin\AclController::class, 'catalog']);
                        Route::get('/acl/plans/{planId}/role-matrix', [\App\Http\Controllers\Api\V1\Admin\AclController::class, 'planRoleMatrix']);
                    });

                });

                // Public Admin Login
                Route::post('/admin/login', [\App\Http\Controllers\Api\V1\AdminController::class, 'login'])
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
