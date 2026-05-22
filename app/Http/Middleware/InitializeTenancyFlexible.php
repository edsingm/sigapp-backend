<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;

/**
 * Middleware de identificação flexível de tenant.
 *
 * Resolve o tenant a partir de:
 * 1. Subdomínio (ex: ed2.localhost ou ed2.sigapp.com.br)
 * 2. Cabeçalho X-Tenant (fallback, útil para clientes de API)
 */
class InitializeTenancyFlexible
{
    protected Tenancy $tenancy;

    public function __construct(Tenancy $tenancy)
    {
        $this->tenancy = $tenancy;
    }

    /**
     * Manipula uma requisição de entrada.
     */
    public function handle(Request $request, Closure $next)
    {
        // Ignora requisições OPTIONS (CORS preflight)
        if ($request->method() === 'OPTIONS') {
            return $next($request);
        }

        $tenantSlug = $this->resolveTenantSlug($request);

        if (! $tenantSlug) {
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

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_NOT_FOUND',
                    'message' => 'Tenant não encontrado.',
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

        // Verifica se o host é um subdomínio de um domínio central
        foreach ($centralDomains as $centralDomain) {
            $centralDomain = strtolower($centralDomain);
            $hostLower = strtolower($host);

            if ($centralDomain === $hostLower) {
                // Correspondência exata = domínio central, sem subdomínio
                continue;
            }

            // Verifica se o host termina com .centralDomain (ex: ed2.localhost ou ed2.sigapp.com.br)
            $suffix = '.'.$centralDomain;
            if (str_ends_with($hostLower, $suffix)) {
                $subdomain = substr($hostLower, 0, -strlen($suffix));
                if ($subdomain && ! str_contains($subdomain, '.')) {
                    return $subdomain;
                }
            }
        }

        if (app()->environment(['local', 'testing', 'development']) && $request->hasHeader('X-Tenant')) {
            $headerValue = (string) $request->header('X-Tenant', '');
            // Aceita apenas slugs alfanuméricos com hífen (sem injeção de caracteres especiais)
            if (preg_match('/^[a-z0-9\-]{1,63}$/i', $headerValue)) {
                return strtolower($headerValue);
            }
        }

        return null;
    }
}
