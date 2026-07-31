<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\TerrenoPolygonStatus;
use App\Models\Tenant\TerrenoPendingPolygon;
use App\Models\Tenant\TerrenoPolygonImport;
use App\Models\Tenant\TerrenoPolygonImportFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<TerrenoPendingPolygon> */
class TerrenoPendingPolygonFactory extends Factory
{
    protected $model = TerrenoPendingPolygon::class;

    public function definition(): array
    {
        return [
            'terreno_polygon_import_id' => TerrenoPolygonImport::factory(),
            'terreno_polygon_import_file_id' => static fn (array $attributes): int => (int) TerrenoPolygonImportFile::factory()
                ->createOne(['terreno_polygon_import_id' => $attributes['terreno_polygon_import_id']])
                ->getKey(),
            'geometry_index' => 0,
            'polygon_coords' => [
                ['lat' => -23.5505, 'lng' => -46.6333],
                ['lat' => -23.5510, 'lng' => -46.6320],
                ['lat' => -23.5520, 'lng' => -46.6340],
            ],
            'geometry_hash' => hash('sha256', fake()->uuid()),
            'min_lat' => -23.5520,
            'max_lat' => -23.5505,
            'min_lng' => -46.6340,
            'max_lng' => -46.6320,
            'status' => TerrenoPolygonStatus::PENDING,
        ];
    }
}
