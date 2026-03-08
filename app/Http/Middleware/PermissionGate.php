<?php

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates that the authenticated tenant user has the required Spatie permission
 * for the module (and optional sub-module) being accessed.
 *
 * Usage in routes:
 *
 *   permission.gate:{module}
 *   permission.gate:{module},{submodule}
 *   permission.gate:{module},null,{action}       ← explicit extra action (e.g. export, approve)
 *
 * The action is inferred from the HTTP method when not provided:
 *   GET  (no route params)  → view_any
 *   GET  (has route params) → view
 *   POST                    → create
 *   PUT / PATCH             → update
 *   DELETE                  → delete
 *
 * Permission name built following AclPermissionCatalogService convention:
 *   Module-level:      "{action} {module}"             → "view any terrenos"
 *   Sub-module level:  "{action} {submodule} {module}" → "view any predio terrenos"
 *   Extra action:      "{action} {module}"             → "export terrenos"
 *
 * Roles super_admin and admin bypass all checks.
 */
class PermissionGate
{
    /** Roles that bypass module-level permission checks entirely. */
    private const BYPASS_ROLES = ['super_admin', 'admin'];

    public function handle(
        Request $request,
        Closure $next,
        string $module,
        ?string $submodule = null,
        ?string $action = null
    ): Response {
        $user = $request->user();

        if (!$user) {
            return ApiResponseService::error('UNAUTHENTICATED', 'Não autenticado.', null, 401);
        }

        // Admins bypass module-level gate
        if ($user->hasAnyRole(self::BYPASS_ROLES)) {
            return $next($request);
        }

        // Normalize "null" string passed as route parameter
        $submodule = ($submodule === null || $submodule === 'null') ? null : $submodule;
        $action    = ($action    === null || $action    === 'null') ? null : $action;

        $permissionName = $this->buildPermissionName($request, $module, $submodule, $action);

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

    /**
     * Builds the Spatie permission name for the current request context.
     */
    private function buildPermissionName(
        Request $request,
        string $module,
        ?string $submodule,
        ?string $action
    ): string {
        $resolvedAction = $action ?? $this->inferActionFromRequest($request);

        $actionLabel  = str_replace('_', ' ', $resolvedAction);
        $moduleLabel  = str_replace('_', ' ', $module);

        if ($submodule !== null) {
            return "{$actionLabel} {$submodule} {$moduleLabel}";
        }

        return "{$actionLabel} {$moduleLabel}";
    }

    /**
     * Infers the Spatie action name from the HTTP method and whether the route
     * has bound parameters (indicating a single-resource operation).
     */
    private function inferActionFromRequest(Request $request): string
    {
        $method = strtoupper($request->method());

        if ($method === 'GET') {
            $route    = $request->route();
            $hasParam = $route !== null && count($route->parameters()) > 0;

            return $hasParam ? 'view' : 'view_any';
        }

        return match ($method) {
            'POST'          => 'create',
            'PUT', 'PATCH'  => 'update',
            'DELETE'        => 'delete',
            default         => 'view_any',
        };
    }
}
