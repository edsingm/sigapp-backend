<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AiRateLimit
{
    /**
     * Manipula uma requisição de entrada.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            return $next($request);
        }

        $tenantId = tenant('id');
        $userId = Auth::id();
        $key = "ai-chat:tenant:{$tenantId}:user:{$userId}";

        $maxAttempts = (int) env('AI_RATE_LIMIT_PER_MINUTE', 30);
        $decayMinutes = 1;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return ApiResponseService::error(
                'AI_RATE_LIMIT_EXCEEDED',
                "Limite de requisições de IA excedido. Tente novamente em {$seconds} segundos.",
                [
                    'retry_after' => $seconds,
                    'limit' => $maxAttempts,
                    'window_minutes' => $decayMinutes,
                ],
                429,
            );
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return $next($request);
    }
}
