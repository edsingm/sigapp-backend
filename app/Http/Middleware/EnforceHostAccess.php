<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\ApiResponseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceHostAccess
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $this->normalizeHost($request->getHost());

        if (in_array($host, $this->centralDomains(), true)) {
            return $next($request);
        }

        $applicationDomain = $this->normalizeHost((string) config('app.domain', 'sigapp.com.br'));
        $tenantSlug = $this->tenantSlug($host, $applicationDomain);

        if ($tenantSlug === null && app()->environment(['local', 'testing', 'development'])) {
            $tenantSlug = $this->tenantSlug($host, 'localhost');
        }

        if ($tenantSlug !== null && $this->tenantRepository->existsBySlug($tenantSlug)) {
            return $next($request);
        }

        if ($tenantSlug !== null && $this->shouldRedirect($request)) {
            return redirect()->away($this->redirectUrl($request, $applicationDomain));
        }

        return ApiResponseService::error(
            'TENANT_NOT_FOUND',
            'TENANT_NOT_FOUND',
            null,
            404,
        );
    }

    /**
     * @return list<string>
     */
    private function centralDomains(): array
    {
        $domains = array_map(
            fn (mixed $domain): string => $this->normalizeHost((string) $domain),
            (array) config('tenancy.identification.central_domains', []),
        );

        return array_values(array_unique(array_filter($domains)));
    }

    private function tenantSlug(string $host, string $applicationDomain): ?string
    {
        if ($applicationDomain === '') {
            return null;
        }

        $suffix = '.'.$applicationDomain;

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        return preg_match('/^[a-z0-9-]{1,63}$/', $subdomain) === 1 ? $subdomain : null;
    }

    private function shouldRedirect(Request $request): bool
    {
        return in_array($request->method(), ['GET', 'HEAD'], true)
            && ! $request->expectsJson()
            && ! $request->is('api/*');
    }

    private function redirectUrl(Request $request, string $applicationDomain): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        if ($frontendUrl === '') {
            $frontendUrl = 'https://app.'.$applicationDomain;
        }

        return $frontendUrl.$request->getRequestUri();
    }

    private function normalizeHost(string $host): string
    {
        return strtolower(trim($host, " \t\n\r\0\x0B."));
    }
}
