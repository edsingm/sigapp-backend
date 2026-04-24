<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\ApiResponseService;
use Illuminate\Support\Str;

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

        $exists = $this->tenantRepository->existsBySlug($normalizedSubdomain)
            || $this->tenantRepository->existsByDomain($normalizedSubdomain);

        return ApiResponseService::success([
            'available' => ! $exists,
            'normalized_subdomain' => $normalizedSubdomain,
        ], $exists ? 'SUBDOMAIN_UNVAVAILABLE' : 'SUBDOMAIN_AVAILABLE');
    }
}
