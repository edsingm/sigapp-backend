<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralContext
{
    /**
     * Garante que a requisição esteja fora do contexto de tenancy.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (tenancy()->initialized) {
            return ApiResponseService::forbidden('Rota disponível apenas no contexto central.');
        }

        return $next($request);
    }
}
