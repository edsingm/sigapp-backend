<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AddTenantContextToLogs
{
    /**
     * Manipula uma requisição de entrada.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Adiciona o contexto do tenant se disponível
        if (tenancy()->initialized) {
            $tenant = tenancy()->tenant;

            Log::withContext([
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'tenant_name' => $tenant->name,
            ]);
        }

        // Adiciona o contexto da requisição
        Log::withContext([
            'request_id' => $request->header('X-Request-ID') ?? uniqid('req_'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $next($request);
    }
}
