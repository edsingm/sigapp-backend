<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\TerrenoImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TerrenoImport */
class TerrenoImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TerrenoImport $import */
        $import = $this->resource;

        return [
            'id' => $import->id,
            'status' => $import->status->value,
            'progress' => $import->progress,
            'file_name' => $import->file_name,
            'counts' => [
                'total' => $import->total_rows,
                'valid' => $import->valid_rows,
                'invalid' => $import->invalid_rows,
                'imported' => $import->imported_rows,
            ],
            'error' => $import->error_code === null ? null : [
                'code' => $import->error_code,
                'message' => language()->t($import->error_message ?? $import->error_code),
            ],
            'requested_at' => $import->requested_at?->toIso8601String(),
            'validated_at' => $import->validated_at?->toIso8601String(),
            'confirmed_at' => $import->confirmed_at?->toIso8601String(),
            'completed_at' => $import->completed_at?->toIso8601String(),
            'expires_at' => $import->expires_at?->toIso8601String(),
        ];
    }
}
