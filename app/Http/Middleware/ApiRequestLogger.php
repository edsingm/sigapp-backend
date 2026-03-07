<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiRequestLogger
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = microtime(true) - $startTime;

        $logData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round($duration * 1000, 2),
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];

        // Add tenant context if available
        if (tenancy()->initialized) {
            $logData['tenant_id'] = tenancy()->tenant->id;
            $logData['tenant_slug'] = tenancy()->tenant->slug;
        }

        // Add user context if authenticated
        if ($request->user()) {
            $logData['user_id'] = $request->user()->id;
        }

        // Highlight auth/authz/rate-limit events with route context.
        if (in_array($response->getStatusCode(), [401, 403, 429], true)) {
            $logData['route'] = $request->route()?->uri();
            Log::channel('tenant')->warning('API Security Response', $logData);
        } elseif ($duration > 1) {
            // Log slow requests as warning
            Log::channel('tenant')->warning('Slow API Request', $logData);
        } else {
            Log::channel('tenant')->info('API Request', $logData);
        }

        return $response;
    }
}
