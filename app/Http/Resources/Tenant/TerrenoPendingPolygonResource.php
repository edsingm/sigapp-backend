<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\TerrenoPendingPolygon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TerrenoPendingPolygon */
class TerrenoPendingPolygonResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TerrenoPendingPolygon $polygon */
        $polygon = $this->resource;
        $coordinates = $polygon->polygon_coords;

        return [
            'type' => 'Feature',
            'id' => $polygon->id,
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    ...array_map(
                        static fn (array $point): array => [(float) $point['lng'], (float) $point['lat']],
                        $coordinates,
                    ),
                    [(float) $coordinates[0]['lng'], (float) $coordinates[0]['lat']],
                ]],
            ],
            'properties' => [
                'id' => $polygon->id,
                'status' => $polygon->status->value,
                'import_id' => $polygon->terreno_polygon_import_id,
                'source_file' => $polygon->file->file_name,
                'source_entry' => $polygon->source_entry,
                'placemark_name' => $polygon->placemark_name,
                'terreno_id' => $polygon->terreno_id,
            ],
        ];
    }
}
