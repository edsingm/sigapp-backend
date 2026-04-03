<?php

namespace App\Services\Modules;

use App\Enums\Common\SectorsEnum;
use App\Models\Central\Modules\Modules;

class ModulesService
{
    public function getAllModules(): array
    {
        $modules = Modules::where('active', true)
            ->orderBy('order', 'asc')
            ->get();

        return $modules
            ->groupBy(fn(Modules $module) => $module->sector->value)
            ->sortBy(fn($_, string $sectorValue) => SectorsEnum::from($sectorValue)->order())
            ->all();
    }
}
