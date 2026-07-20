<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use Illuminate\Support\Str;

class SubdomainPolicyService
{
    public function isReserved(string $subdomain): bool
    {
        $normalizedSubdomain = Str::slug($subdomain);

        if ($normalizedSubdomain === '') {
            return true;
        }

        return in_array($normalizedSubdomain, $this->reservedSubdomains(), true);
    }

    /**
     * @return list<string>
     */
    private function reservedSubdomains(): array
    {
        $reserved = array_map(
            static fn (mixed $subdomain): string => Str::slug((string) $subdomain),
            (array) config('tenancy.identification.reserved_subdomains', []),
        );

        $applicationDomain = $this->normalizeHost((string) config('app.domain', 'sigapp.com.br'));

        foreach ((array) config('tenancy.identification.central_domains', []) as $centralDomain) {
            $centralDomain = $this->normalizeHost((string) $centralDomain);
            $suffix = '.'.$applicationDomain;

            if ($applicationDomain === '' || ! str_ends_with($centralDomain, $suffix)) {
                continue;
            }

            $subdomain = substr($centralDomain, 0, -strlen($suffix));

            if ($subdomain !== '' && ! str_contains($subdomain, '.')) {
                $reserved[] = Str::slug($subdomain);
            }
        }

        return array_values(array_unique(array_filter($reserved)));
    }

    private function normalizeHost(string $host): string
    {
        return strtolower(trim($host, " \t\n\r\0\x0B."));
    }
}
