<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\TerrenoImportRowStatus;
use App\Models\Tenant\TerrenoImport;
use App\Models\Tenant\TerrenoImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<TerrenoImportRow> */
class TerrenoImportRowFactory extends Factory
{
    protected $model = TerrenoImportRow::class;

    public function definition(): array
    {
        return [
            'terreno_import_id' => TerrenoImport::factory(),
            'row_number' => 2,
            'raw_data' => ['nome' => fake()->company()],
            'normalized_data' => ['nome' => fake()->company()],
            'status' => TerrenoImportRowStatus::VALID,
        ];
    }
}
