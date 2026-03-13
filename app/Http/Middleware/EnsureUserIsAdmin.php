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
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user instanceof User) {
            return ApiResponseService::forbidden('Acesso restrito a administradores centrais.');
        }

        $token = $user->currentAccessToken();
        $hasAdminAbility = $token && $token->can('admin');

        if (!$user->is_admin || !$hasAdminAbility) {
            return ApiResponseService::forbidden('Acesso restrito a administradores centrais.');
        }

        return $next($request);
    }
}
