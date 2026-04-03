<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Services\ApiResponseService;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class PublicTenantController extends Controller
{
    /**
     * Verificar a disponibilidade de um subdomínio (slug).
     */
    public function subdomainAvailability(string $subdomain)
    {
        $normalizedSubdomain = Str::slug($subdomain);

        $exists = Tenant::query()
            ->where('slug', $normalizedSubdomain)
            ->exists();

        $exists = $exists || Domain::query()
            ->where('domain', $normalizedSubdomain)
            ->exists();

        return ApiResponseService::success([
            'available' => !$exists,
            'normalized_subdomain' => $normalizedSubdomain,
        ], $exists ? 'SUBDOMAIN_UNVAVAILABLE' : 'SUBDOMAIN_AVAILABLE');
    }
}
