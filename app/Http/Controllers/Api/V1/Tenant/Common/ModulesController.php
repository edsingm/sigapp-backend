<?php

namespace App\Http\Controllers\Api\V1\Tenant\Common;

use App\Enums\Common\SectorsEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Http\Resources\Tenant\Modules\ModulesResource;
use App\Services\ApiResponseService;
use App\Services\Modules\ModulesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModulesController extends Controller
{
    public function __construct(
        private readonly ModulesService $modulesService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $grouped = $this->modulesService->getAllModules();

        $modules = [];

        foreach ($grouped as $sectorValue => $moduleCollection) {
            $sector = SectorsEnum::from($sectorValue);

            $modules[] = [
                'sector'  => [
                    'slug'  => $sector->value,
                    'label' => $sector->label(),
                    'order' => $sector->order(),
                ],
                'modules' => $moduleCollection
                    ->map(fn($m) => (new ModulesResource($m))->toArray($request))
                    ->values()
                    ->all(),
            ];
        }

        $tenant = tenancy()->tenant;
        $tenant->load('plan');

        $user = $request->user();

        return ApiResponseService::success([
            'tenant'  => new TenantResource($tenant),
            'user'    => [
                'roles'       => $user->getRoleNames()->values()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            ],
            'modules' => $modules,
        ]);
    }
}
