<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Central\Tenant;
use Stancl\Tenancy\Tenancy;

/**
 * Flexible tenant identification middleware.
 *
 * Resolves tenant from:
 * 1. Subdomain (e.g., ed2.localhost or ed2.sigapp.com.br)
 * 2. X-Tenant header (fallback, useful for API clients)
 */
class InitializeTenancyFlexible
{
    protected Tenancy $tenancy;

    public function __construct(Tenancy $tenancy)
    {
        $this->tenancy = $tenancy;
    }

    public function handle(Request $request, Closure $next)
    {
        // Skip OPTIONS requests (CORS preflight)
        if ($request->method() === 'OPTIONS') {
            return $next($request);
        }

        $tenantSlug = $this->resolveTenantSlug($request);

        if (!$tenantSlug) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_NOT_IDENTIFIED',
                    'message' => 'Não foi possível identificar o tenant.',
                ],
            ], 404);
        }

        $tenant = Tenant::where('slug', $tenantSlug)
            ->orWhere('id', $tenantSlug)
            ->first();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_NOT_FOUND',
                    'message' => "Tenant '{$tenantSlug}' não encontrado.",
                ],
            ], 404);
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }

    protected function resolveTenantSlug(Request $request): ?string
    {
        $host = $request->getHost();
        $centralDomains = config('tenancy.identification.central_domains', []);

        // Check if host is a subdomain of a central domain
        foreach ($centralDomains as $centralDomain) {
            $centralDomain = strtolower($centralDomain);
            $hostLower = strtolower($host);

            if ($centralDomain === $hostLower) {
                // Exact match = central domain, no subdomain
                continue;
            }

            // Check if host ends with .centralDomain (e.g., ed2.localhost or ed2.sigapp.com.br)
            $suffix = '.' . $centralDomain;
            if (str_ends_with($hostLower, $suffix)) {
                $subdomain = substr($hostLower, 0, -strlen($suffix));
                if ($subdomain && !str_contains($subdomain, '.')) {
                    return $subdomain;
                }
            }
        }

        if (app()->environment(['local', 'testing']) && $request->hasHeader('X-Tenant')) {
            return $request->header('X-Tenant');
        }

        return null;
    }
}
