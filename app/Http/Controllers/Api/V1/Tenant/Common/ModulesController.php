<?php

namespace App\Http\Controllers\Api\V1\Tenant\Common;

use App\Enums\Common\SectorsEnum;
use App\Http\Controllers\Controller;
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

        $data = [];

        foreach ($grouped as $sectorValue => $modules) {
            $sector = SectorsEnum::from($sectorValue);

            $data[] = [
                'sector'  => [
                    'slug'  => $sector->value,
                    'label' => $sector->label(),
                    'order' => $sector->order(),
                ],
                'modules' => $modules
                    ->map(fn($m) => (new ModulesResource($m))->toArray($request))
                    ->values()
                    ->all(),
            ];
        }

        return ApiResponseService::success($data);
    }
}
