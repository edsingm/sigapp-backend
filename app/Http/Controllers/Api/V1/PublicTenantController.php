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
        ], $exists ? 'Subdomínio indisponível' : 'Subdomínio disponível');
    }

    public function bySubdomain(string $subdomain)
    {
        $tenant = Tenant::where('slug', $subdomain)
            ->where('status', Tenant::STATUS_ACTIVE)
            ->first();

        if (!$tenant) {
            return ApiResponseService::notFound('Tenant não encontrado');
        }

        return ApiResponseService::success([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
        ], 'Tenant encontrado com sucesso');
    }

    public function discover(Request $request)
    {
        return ApiResponseService::error(
            'DEPRECATED_ENDPOINT',
            'Fluxo substituído por login central com email e senha (/api/v1/auth/central-login).',
            null,
            410
        );
    }
}
