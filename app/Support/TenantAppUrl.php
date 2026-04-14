<?php

namespace App\Support;

use App\Models\Central\Tenant;

class TenantAppUrl
{
    public function loginUrl(Tenant $tenant): string
    {
        return $this->build($tenant, '/login');
    }

    /**
     * @param  array<string, string>  $query
     */
    public function resetPasswordUrl(Tenant $tenant, array $query): string
    {
        return $this->build($tenant, '/reset-password', $query);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function build(Tenant $tenant, string $path, array $query = []): string
    {
        $frontendUrl = (string) config('app.frontend_url', config('app.url', 'http://localhost:8080'));
        $parts = parse_url($frontendUrl);

        $scheme = is_string($parts['scheme'] ?? null) && $parts['scheme'] !== ''
            ? $parts['scheme']
            : (app()->environment('local') ? 'http' : 'https');

        $host = is_string($parts['host'] ?? null) && $parts['host'] !== ''
            ? $parts['host']
            : 'localhost';

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $tenantHost = $this->resolveTenantHost($tenant, $host);
        $normalizedPath = '/'.ltrim($path, '/');
        $queryString = $query !== [] ? '?'.http_build_query($query) : '';

        return "{$scheme}://{$tenantHost}{$port}{$normalizedPath}{$queryString}";
    }

    private function resolveTenantHost(Tenant $tenant, string $frontendHost): string
    {
        $domain = $tenant->domains()->orderBy('id')->value('domain');

        if (is_string($domain) && $domain !== '') {
            $domainHost = parse_url($domain, PHP_URL_HOST);

            return is_string($domainHost) && $domainHost !== ''
                ? $domainHost
                : preg_replace('#^https?://#', '', rtrim($domain, '/'));
        }

        $normalizedHost = strtolower($frontendHost);

        if ($normalizedHost === 'localhost' || $normalizedHost === '127.0.0.1') {
            return "{$tenant->slug}.localhost";
        }

        if (str_starts_with($normalizedHost, 'www.')) {
            $normalizedHost = substr($normalizedHost, 4);
        }

        $centralDomains = array_map(
            static fn (string $value): string => strtolower(trim($value)),
            array_filter(config('tenancy.identification.central_domains', []), 'is_string'),
        );

        foreach ($centralDomains as $centralDomain) {
            if ($centralDomain === '' || $centralDomain === 'localhost' || $centralDomain === '127.0.0.1') {
                continue;
            }

            if ($normalizedHost === $centralDomain || $normalizedHost === "www.{$centralDomain}") {
                return "{$tenant->slug}.{$centralDomain}";
            }
        }

        return "{$tenant->slug}.{$normalizedHost}";
    }
}
