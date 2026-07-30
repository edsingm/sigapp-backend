<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\TerrenoPolygonImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TerrenoPolygonImport */
class TerrenoPolygonImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TerrenoPolygonImport $import */
        $import = $this->resource;

        return [
            'id' => $import->id,
            'status' => $import->status->value,
            'progress' => $import->progress,
            'counts' => [
                'files' => $import->total_files,
                'processed_files' => $import->processed_files,
                'failed_files' => $import->failed_files,
                'polygons' => $import->polygon_count,
            ],
            'files' => $this->when($import->relationLoaded('files'), fn () => $import->files->map(fn ($file): array => [
                'id' => $file->id,
                'file_name' => $file->file_name,
                'status' => $file->status,
                'error_message' => $file->error_message,
            ])),
            'error' => $import->error_code === null ? null : [
                'code' => $import->error_code,
                'message' => language()->t($import->error_message ?? $import->error_code),
            ],
            'requested_at' => $import->requested_at?->toIso8601String(),
            'completed_at' => $import->completed_at?->toIso8601String(),
        ];
    }
}
