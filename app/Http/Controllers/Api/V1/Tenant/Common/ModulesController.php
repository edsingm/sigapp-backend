<?php

namespace App\Http\Controllers\Api\V1\Tenant\Common;

use App\Enums\Common\ModulesEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Modules\ModulesResource;
use App\Services\Modules\ModulesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ModulesController extends Controller
{
    public function __construct(
        private readonly ModulesService $modulesService
    ) {}

    public function index()
    {
        return ModulesResource::collection($this->modulesService->getAllModules());

        return response()->json([
            'data' => array_map(fn($module) => [
                'name' => $module->name,
                'label' => $module->label(),
                'resources' => $module->submodules()
            ], $modules)
        ]);
    }
}
