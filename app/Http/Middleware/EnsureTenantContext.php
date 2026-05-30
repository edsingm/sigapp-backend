<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    /**
     * Garante que a requisição esteja dentro do contexto de tenancy.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            return ApiResponseService::forbidden('Rota disponível apenas no contexto do tenant.');
        }

        return $next($request);
    }
}
