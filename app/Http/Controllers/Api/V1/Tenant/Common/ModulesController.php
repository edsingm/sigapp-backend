<?php

namespace App\Http\Controllers\Api\V1\Tenant\Common;

use App\Enums\Common\SectorsEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Modules\ModulesResource;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Services\ApiResponseService;
use App\Services\Modules\ModuleAccessService;
use App\Services\Modules\ModulesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModulesController extends Controller
{
    public function __construct(
        private readonly ModulesService $modulesService,
        private readonly ModuleAccessService $moduleAccessService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenant = tenancy()->tenant;
        if ($tenant) {
            $tenant->load('plan');
        }

        $user = $request->user();

        $groupedModules = $this->modulesService->getAllModules();
        $payload = [
            'tenant' => $tenant ? new TenantResource($tenant) : null,
            'user' => $user ? new UserResource($user) : null,
            'modules' => $this->serializedModules($request, $groupedModules),
        ];

        if ($tenant && $user) {
            $payload['access'] = $this->moduleAccessService->resolve($tenant, $user, $groupedModules);
        }

        return ApiResponseService::success($payload);
    }

    public function modules(Request $request): JsonResponse
    {
        return ApiResponseService::success($this->serializedModules(
            $request,
            $this->modulesService->getAllModules(),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializedModules(Request $request, ?array $groupedModules = null): array
    {
        $modules = [];

        foreach ($groupedModules ?? $this->modulesService->getAllModules() as $sectorValue => $moduleCollection) {
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
