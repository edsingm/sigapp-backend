<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\ApiResponseService;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class PublicTenantController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Verificar a disponibilidade de um subdomínio (slug).
     */
    public function subdomainAvailability(string $subdomain)
    {
        $normalizedSubdomain = Str::slug($subdomain);

        $tenant = $this->tenantRepository->findBySlug($normalizedSubdomain);
        $domain = Domain::query()->where('domain', $normalizedSubdomain)->first();

        $expiredPending = $tenant
            && $tenant->status === Tenant::STATUS_PENDING
            && $tenant->created_at->lt(now()->subDay());

        $tenantReserved = $tenant ? ! $expiredPending : false;

        $domainReserved = false;
        if ($domain) {
            $domainReserved = ! ($expiredPending && $tenant && (string) $domain->tenant_id === (string) $tenant->id);
        }

        $exists = $tenantReserved || $domainReserved;

        $messageKey = 'SUBDOMAIN_AVAILABLE';
        if ($exists) {
            $messageKey = ($tenant && $tenant->status === Tenant::STATUS_PENDING && ! $expiredPending)
                ? 'SUBDOMAIN_RESERVED'
                : 'SUBDOMAIN_UNVAVAILABLE';
        }

        return ApiResponseService::success([
            'available' => ! $exists,
            'normalized_subdomain' => $normalizedSubdomain,
        ], $messageKey);
    }
}
