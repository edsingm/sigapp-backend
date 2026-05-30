<?php

namespace App\Http\Middleware;

use App\Models\Tenant\User;
use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantUser
{
    /**
     * Garante que o usuário autenticado seja um usuário do tenant atual.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponseService::forbidden('Autenticação restrita a usuários do tenant.');
        }

        if (! tenancy()->initialized) {
            return ApiResponseService::forbidden('Autenticação restrita a usuários do tenant.');
        }

        return $next($request);
    }
}
