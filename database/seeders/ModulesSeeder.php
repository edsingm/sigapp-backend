<?php

namespace Database\Seeders;

use App\Enums\Common\ModulesEnum;
use App\Models\Central\Modules\Modules;
use Illuminate\Database\Seeder;

class ModulesSeeder extends Seeder
{
    public function run(): void
    {
        $data = collect(ModulesEnum::cases())
            ->map(fn(ModulesEnum $module) => [
                'slug'      => $module->value,
                'resources' => $module->hasSubmodules()
                    ? json_encode(array_map(fn($s) => $s->value, $module->submodules()))
                    : null,
                'active'    => true,
                'order'     => $module->order(),
            ])
            ->all();

        Modules::upsert(
            $data,
            uniqueBy: ['slug'],
            update: ['resources', 'active', 'order'],
        );
    }
}
