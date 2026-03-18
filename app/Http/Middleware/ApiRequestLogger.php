<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiRequestLogger
{
    /**
     * Manipula uma requisição de entrada.
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

        // Adiciona o contexto do tenant se disponível
        if (tenancy()->initialized) {
            $logData['tenant_id'] = tenancy()->tenant->id;
            $logData['tenant_slug'] = tenancy()->tenant->slug;
        }

        // Adiciona o contexto do usuário se autenticado
        if ($request->user()) {
            $logData['user_id'] = $request->user()->id;
        }

        // Destaca eventos de auth/authz/rate-limit com o contexto da rota.
        if (in_array($response->getStatusCode(), [401, 403, 429], true)) {
            $logData['route'] = $request->route()?->uri();
            Log::channel('tenant')->warning('Resposta de Segurança da API', $logData);
        } elseif ($duration > 1) {
            // Loga requisições lentas como aviso
            Log::channel('tenant')->warning('Requisição de API Lenta', $logData);
        } else {
            Log::channel('tenant')->info('Requisição de API', $logData);
        }

        return $response;
    }
}
