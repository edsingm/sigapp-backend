<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Services\ApiResponseService;

class PublicTenantController extends Controller
{
    public function subdomainAvailability(string $subdomain)
    {
        $exists = Tenant::where('slug', $subdomain)
            ->where('status', Tenant::STATUS_ACTIVE)
            ->exists();

        return ApiResponseService::success([
            'available' => !$exists,
        ], $exists ? 'SUBDOMAIN_UNVAVAILABLE' : 'SUBDOMAIN_AVAILABLE');
    }
}
