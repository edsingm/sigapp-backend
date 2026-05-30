<?php

namespace App\Http\Middleware;

use App\Enums\Common\RolesEnum;
use App\Models\Tenant\User;
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

        $allowedRoles = [
            RolesEnum::ADMIN->value,
            RolesEnum::DIRECTOR->value,
            'admin',
            'director',
        ];

        if (! $user instanceof User || ! $user->hasAnyRole($allowedRoles)) {
            return ApiResponseService::forbidden('Acesso restrito a administradores do tenant.');
        }

        if (! tenancy()->initialized) {
            return ApiResponseService::forbidden('Acesso restrito a administradores do tenant.');
        }

        return $next($request);
    }
}
