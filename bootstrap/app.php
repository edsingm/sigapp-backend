<?php

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\AiBudgetCheck;
use App\Http\Middleware\AiRateLimit;
use App\Http\Middleware\AiTelemetryMiddleware;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnforcePlanLimits;
use App\Http\Middleware\EnsureCentralContext;
use App\Http\Middleware\EnsureCentralUser;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Http\Middleware\PermissionGate;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Add global middleware aliases
        $middleware->alias([
            'force.json' => ForceJsonResponse::class,
            'tenant.logs' => AddTenantContextToLogs::class,
            'api.logger' => ApiRequestLogger::class,
            'central.context' => EnsureCentralContext::class,
            'tenant.context' => EnsureTenantContext::class,
            'auth.central' => EnsureCentralUser::class,
            'auth.tenant' => EnsureTenantUser::class,
            'enforce.limits' => EnforcePlanLimits::class,
            'subscription.active' => CheckSubscriptionStatus::class,
            'central.admin' => EnsureUserIsAdmin::class,
            'tenant.admin' => EnsureTenantAdmin::class,
            'user.admin' => EnsureUserIsAdmin::class,
            'permission.gate' => PermissionGate::class,
            'check.feature' => CheckFeature::class,
            'ai.rate_limit' => AiRateLimit::class,
            'ai.budget' => AiBudgetCheck::class,
            'ai.telemetry' => AiTelemetryMiddleware::class,
        ]);

        // Register the 'tenant' middleware group
        // Uses subdomain in production, falls back to X-Tenant header for localhost (php artisan serve)
        $middleware->group('tenant', [
            InitializeTenancyFlexible::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Handle AuthenticationException — prevents redirect to route('login') on tenant/API routes
        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Não autenticado.',
                ],
            ], 401);
        });

        // Handle ValidationException for API
        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                $message = 'Os dados fornecidos são inválidos';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => $e->errors(),
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => $message,
                        'details' => $e->errors(),
                    ],
                ], 422);
            }
        });

        $exceptions->renderable(function (RateLimitedException $e, Request $request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AI_PROVIDER_RATE_LIMITED',
                    'message' => 'O provedor de IA atingiu o limite de requisições. Aguarde alguns segundos e tente novamente.',
                ],
            ], 429);
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
        $exceptions->renderable(function (Throwable $e, Request $request) {
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
