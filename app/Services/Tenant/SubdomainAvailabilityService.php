<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Central\Tenant;
use App\Repositories\Contracts\DomainRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Support\Str;

class SubdomainAvailabilityService
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly DomainRepositoryInterface $domainRepository,
    ) {}

    /**
     * @return array{available: bool, normalized_subdomain: string, message_key: string}
     */
    public function check(string $subdomain): array
    {
        $normalizedSubdomain = Str::slug($subdomain);

        $tenant = $this->tenantRepository->findBySlug($normalizedSubdomain);
        $domain = $this->domainRepository->findByDomain($normalizedSubdomain);

        $tenantStatus = $tenant instanceof Tenant ? (string) $tenant->getAttribute('status') : null;

        $expiredPending = $tenant
            && $tenantStatus === Tenant::STATUS_PENDING
            && $tenant->created_at->lt(now()->subDay());

        $tenantReserved = $tenant ? ! $expiredPending : false;

        $domainReserved = false;
        if ($domain) {
            $domainReserved = ! ($expiredPending && $tenant && (string) $domain->tenant_id === (string) $tenant->id);
        }

        $exists = $tenantReserved || $domainReserved;

        $messageKey = 'SUBDOMAIN_AVAILABLE';
        if ($exists) {
            $messageKey = ($tenant && $tenantStatus === Tenant::STATUS_PENDING && ! $expiredPending)
                ? 'SUBDOMAIN_RESERVED'
                : 'SUBDOMAIN_UNVAVAILABLE';
        }

        return [
            'available' => ! $exists,
            'normalized_subdomain' => $normalizedSubdomain,
            'message_key' => $messageKey,
        ];
    }
}
