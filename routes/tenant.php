<?php

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SetUserLocale;
use App\Models\Central\Tenant as CentralTenant;
use App\Services\HealthCheckService;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Tenant Application
|--------------------------------------------------------------------------
|
| The common tenant middleware lives here. Domain route declarations are
| loaded from routes/tenant/ in their original registration order.
|
*/

Route::middleware([
    ForceJsonResponse::class,
    AddTenantContextToLogs::class,
    ApiRequestLogger::class,
])->group(function () {
    Route::prefix('api/v1')->group(function () {
        require __DIR__.'/tenant/public-auth.php';

        Route::middleware([
            'tenant.context',
            'auth:sanctum',
            'auth.tenant',
            'throttle:api-auth',
            SetUserLocale::class,
        ])->group(function () {
            require __DIR__.'/tenant/account-billing.php';

            Route::middleware(CheckSubscriptionStatus::class)->group(function () {
                require __DIR__.'/tenant/workspace-admin.php';
                require __DIR__.'/tenant/prospection.php';
                require __DIR__.'/tenant/viability-ai.php';
                require __DIR__.'/tenant/projects-committee.php';
                require __DIR__.'/tenant/negotiation.php';
                require __DIR__.'/tenant/platform-legal.php';
            });
        });
    });

    // Tenant health check (requer autenticação para não vazar dados do tenant)
    Route::middleware(['auth:sanctum'])->get('/api/health', function (HealthCheckService $health) {
        $tenant = tenancy()->tenant;

        if (! $tenant instanceof CentralTenant) {
            return response()->json([
                'status' => 'error',
                'timestamp' => now()->toIso8601String(),
                'tenant' => null,
            ], 503);
        }

        $report = $health->check();
        $report['tenant'] = [
            'id' => $tenant->id,
            'name' => (string) $tenant->getAttribute('name'),
            'status' => (string) $tenant->getAttribute('status'),
        ];

        $statusCode = $report['status'] === 'down' ? 503 : 200;

        return response()->json($report, $statusCode);
    });
});
