<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AddTenantContextToLogs
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Add tenant context if available
        if (tenancy()->initialized) {
            $tenant = tenancy()->tenant;

            Log::withContext([
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'tenant_name' => $tenant->name,
            ]);
        }

        // Add request context
        Log::withContext([
            'request_id' => $request->header('X-Request-ID') ?? uniqid('req_'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $next($request);
    }
}
