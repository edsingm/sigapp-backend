<?php

namespace App\Http\Middleware;

use App\Services\Acl\PermissionNameResolver;
use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida se o usuário autenticado do tenant possui a permissão necessária
 * para o módulo fornecido (e recurso opcional).
 *
 * Formato da permissão (notação de ponto):
 *   Com recurso:      {modulo}.{recurso}.{nivel}   ex: prospection.terrains.viewer
 *   Sem recurso:      {modulo}.{nivel}              ex: viability.viewer
 *
 * Método HTTP → nível mínimo exigido:
 *   GET            → viewer
 *   POST/PUT/PATCH → editor
 *   DELETE         → manager
 *
 * Uso nas rotas:
 *   permission.gate:{modulo}
 *   permission.gate:{modulo},{recurso}
 *
 * O papel (role) ADMIN canônico ignora todas as verificações.
 */
class PermissionGate
{
    public function __construct(
        private readonly PermissionNameResolver $permissions,
    ) {}

    /**
     * Manipula uma requisição de entrada.
     */
    public function handle(
        Request $request,
        Closure $next,
        string $module,
        ?string $resource = null
    ): Response {
        $user = $request->user();

        if (! $user) {
            return ApiResponseService::error('UNAUTHENTICATED', 'Não autenticado.', null, 401);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        $permissionName = $this->permissions->forRequest($module, $resource, $request->method());

        if (! $user->hasPermissionTo($permissionName)) {
            return ApiResponseService::error(
                'FORBIDDEN',
                'Você não tem permissão para realizar esta ação.',
                ['required_permission' => $permissionName],
                403
            );
        }

        return $next($request);
    }
}
