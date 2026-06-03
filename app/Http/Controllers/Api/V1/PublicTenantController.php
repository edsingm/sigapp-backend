<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\Tenant\SubdomainAvailabilityService;

class PublicTenantController extends Controller
{
    public function __construct(
        private readonly SubdomainAvailabilityService $subdomainAvailability,
    ) {}

    /**
     * Verificar a disponibilidade de um subdomínio (slug).
     */
    public function subdomainAvailability(string $subdomain)
    {
        $result = $this->subdomainAvailability->check($subdomain);

        return ApiResponseService::success([
            'available' => $result['available'],
            'normalized_subdomain' => $result['normalized_subdomain'],
        ], $result['message_key']);
    }
}
