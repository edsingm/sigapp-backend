<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

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

    public function bySubdomain(string $subdomain)
    {
        $tenant = Tenant::where('slug', $subdomain)
            ->where('status', Tenant::STATUS_ACTIVE)
            ->first();

        if (!$tenant) {
            return ApiResponseService::notFound('TENANT_NOT_FOUND');
        }

        return ApiResponseService::success([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
        ], 'TENANT_DATA_RETRIEVED');
    }

    public function discover(Request $request)
    {
        return ApiResponseService::error(
            'DEPRECATED_ENDPOINT',
            'USE_CENTRAL_LOGIN_FLOW',
            null,
            410
        );
    }
}
