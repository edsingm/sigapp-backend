<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\TerrenoPolygonImport;
use App\Models\Tenant\TerrenoPolygonImportFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<TerrenoPolygonImportFile> */
class TerrenoPolygonImportFileFactory extends Factory
{
    protected $model = TerrenoPolygonImportFile::class;

    public function definition(): array
    {
        return [
            'terreno_polygon_import_id' => TerrenoPolygonImport::factory(),
            'file_name' => 'poligonos.kml',
            'status' => 'queued',
        ];
    }
}
