<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add global middleware aliases
        $middleware->alias([
            'force.json' => \App\Http\Middleware\ForceJsonResponse::class,
            'tenant.logs' => \App\Http\Middleware\AddTenantContextToLogs::class,
            'api.logger' => \App\Http\Middleware\ApiRequestLogger::class,
            'enforce.limits' => \App\Http\Middleware\EnforcePlanLimits::class,
            'subscription.active' => \App\Http\Middleware\CheckSubscriptionStatus::class,
            'tenant.admin' => \App\Http\Middleware\EnsureTenantAdmin::class,
            'user.admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'require.entitlement' => \App\Http\Middleware\RequireEntitlement::class,
            'permission.gate'     => \App\Http\Middleware\PermissionGate::class,
        ]);

        // Register the 'tenant' middleware group
        // Uses subdomain in production, falls back to X-Tenant header for localhost (php artisan serve)
        $middleware->group('tenant', [
            \App\Http\Middleware\InitializeTenancyFlexible::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Handle ValidationException for API
        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Os dados fornecidos são inválidos',
                        'details' => $e->errors(),
                    ],
                ], 422);
            }
        });

        // Handle HttpException for API
        $exceptions->renderable(function (HttpException $e, Request $request) {
            if ($request->expectsJson()) {
                $statusCode = $e->getStatusCode();
                $errorCodes = [
                    401 => 'UNAUTHORIZED',
                    403 => 'FORBIDDEN',
                    404 => 'NOT_FOUND',
                    409 => 'CONFLICT',
                    429 => 'TOO_MANY_REQUESTS',
                    500 => 'INTERNAL_SERVER_ERROR',
                    503 => 'SERVICE_UNAVAILABLE',
                ];

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => $errorCodes[$statusCode] ?? 'HTTP_ERROR',
                        'message' => $e->getMessage() ?: match ($statusCode) {
                            401 => 'Não autenticado',
                            403 => 'Sem permissão',
                            404 => 'Recurso não encontrado',
                            429 => 'Muitas requisições',
                            500 => 'Erro interno do servidor',
                            503 => 'Serviço indisponível',
                            default => 'Erro desconhecido',
                        },
                    ],
                ], $statusCode);
            }
        });

        // Handle NotFoundHttpException specifically
        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Rota ou recurso não encontrado',
                    ],
                ], 404);
            }
        });

        // Handle all other exceptions in production (API only)
        $exceptions->renderable(function (\Throwable $e, Request $request) {
            if ($request->expectsJson() && app()->environment('production')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INTERNAL_ERROR',
                        'message' => 'Erro interno do servidor',
                    ],
                ], 500);
            }
        });

    })->create();
