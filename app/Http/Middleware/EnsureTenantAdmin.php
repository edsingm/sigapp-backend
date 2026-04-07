<?php

namespace App\Http\Middleware;

use App\Enums\Common\RolesEnum;
use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAdmin
{
    /**
     * Garante que o usuário autenticado do tenant possua privilégios de administrador.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole([RolesEnum::ADMIN->value, RolesEnum::DIRECTOR->value])) {
            return ApiResponseService::forbidden('Acesso restrito a administradores do tenant.');
        }

        return $next($request);
    }
}
