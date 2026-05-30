<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Manipula uma requisição de entrada.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (tenancy()->initialized || ! $user instanceof User) {
            return ApiResponseService::forbidden('Acesso restrito a administradores centrais.');
        }

        $token = $user->currentAccessToken();
        $hasAdminAbility = $token && $token->can('admin');

        if (! $user->is_admin || ! $hasAdminAbility) {
            return ApiResponseService::forbidden('Acesso restrito a administradores centrais.');
        }

        return $next($request);
    }
}
