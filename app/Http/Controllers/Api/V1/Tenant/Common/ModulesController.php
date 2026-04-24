<?php

namespace App\Http\Controllers\Api\V1\Tenant\Common;

use App\Enums\Common\SectorsEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Modules\ModulesResource;
use App\Http\Resources\TenantResource;
use App\Services\ApiResponseService;
use App\Services\Modules\ModulesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModulesController extends Controller
{
    public function __construct(
        private readonly ModulesService $modulesService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenant = tenancy()->tenant;
        if ($tenant) {
            $tenant->load('plan');
        }

        $user = $request->user();

        return ApiResponseService::success([
            'tenant' => $tenant ? new TenantResource($tenant) : null,
            'user' => [
                'roles' => $user?->getRoleNames()->values()->all() ?? [],
                'permissions' => $user?->getAllPermissions()->pluck('name')->values()->all() ?? [],
            ],
            'modules' => $this->serializedModules($request),
        ]);
    }

    public function modules(Request $request): JsonResponse
    {
        return ApiResponseService::success($this->serializedModules($request));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializedModules(Request $request): array
    {
        $modules = [];

        foreach ($this->modulesService->getAllModules() as $sectorValue => $moduleCollection) {
            $sector = SectorsEnum::from($sectorValue);

            $modules[] = [
                'sector' => [
                    'slug' => $sector->value,
                    'label' => $sector->label(),
                    'order' => $sector->order(),
                ],
                'modules' => $moduleCollection
                    ->map(fn ($module) => (new ModulesResource($module))->toArray($request))
                    ->values()
                    ->all(),
            ];
        }

        return $modules;
    }
}
