<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates that the authenticated tenant user has the required permission
 * for the given module (and optional resource).
 *
 * Permission format (dot-notation):
 *   With resource:    {module}.{resource}.{level}   e.g. prospection.terrains.viewer
 *   Without resource: {module}.{level}              e.g. viability.viewer
 *
 * HTTP method → minimum required level:
 *   GET            → viewer
 *   POST/PUT/PATCH → editor
 *   DELETE         → manager
 *
 * Usage in routes:
 *   permission.gate:{module}
 *   permission.gate:{module},{resource}
 *
 * Roles SUPER_ADMIN and ADMIN bypass all checks.
 */
class PermissionGate
{
    private const BYPASS_ROLES = ['SUPER_ADMIN', 'ADMIN', 'super_admin', 'admin'];

    private const METHOD_LEVEL_MAP = [
        'GET'    => 'viewer',
        'POST'   => 'editor',
        'PUT'    => 'editor',
        'PATCH'  => 'editor',
        'DELETE' => 'manager',
    ];

    public function handle(
        Request $request,
        Closure $next,
        string $module,
        ?string $resource = null
    ): Response {
        $user = $request->user();

        if (!$user) {
            return ApiResponseService::error('UNAUTHENTICATED', 'Não autenticado.', null, 401);
        }

        if ($user->hasAnyRole(self::BYPASS_ROLES)) {
            return $next($request);
        }

        $level          = self::METHOD_LEVEL_MAP[strtoupper($request->method())] ?? 'viewer';
        $permissionName = $resource !== null
            ? "{$module}.{$resource}.{$level}"
            : "{$module}.{$level}";

        if (!$user->hasPermissionTo($permissionName)) {
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
