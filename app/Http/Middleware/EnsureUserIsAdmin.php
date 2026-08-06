<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
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
        $hasAdminAbility = $this->hasAbility($token, 'admin');
        $hasMfaAbility = $this->hasAbility($token, 'admin:mfa');

        if (! $user->is_admin || ! $user->admin_mfa_confirmed_at || ! $hasAdminAbility || ! $hasMfaAbility) {
            return ApiResponseService::forbidden('Acesso restrito a administradores centrais.');
        }

        return $next($request);
    }

    private function hasAbility(?object $token, string $ability): bool
    {
        return $token instanceof PersonalAccessToken && $token->can($ability);
    }
}
