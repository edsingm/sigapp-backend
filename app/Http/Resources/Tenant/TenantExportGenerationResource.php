<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Enums\TenantExportStatus;
use App\Models\Tenant\TenantExportGeneration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TenantExportGeneration */
class TenantExportGenerationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TenantExportGeneration $generation */
        $generation = $this->resource;
        $available = $generation->status === TenantExportStatus::COMPLETED
            && ! ($generation->expires_at?->isPast() ?? true);

        return [
            'id' => $generation->id,
            'type' => $generation->type->value,
            'subject_id' => $generation->subject_id,
            'status' => $generation->status->value,
            'progress' => $generation->progress,
            'file_name' => $available ? $generation->file_name : null,
            'mime_type' => $available ? $generation->mime_type : null,
            'size' => $available ? $generation->size : null,
            'download_url' => $available
                ? route('tenant.exports.download', ['export' => $generation->id])
                : null,
            'error_message' => $generation->status === TenantExportStatus::FAILED
                ? language()->t($generation->error_message ?? 'EXPORT_GENERATION_FAILED')
                : null,
            'requested_at' => $generation->requested_at?->toIso8601String(),
            'started_at' => $generation->started_at?->toIso8601String(),
            'completed_at' => $generation->completed_at?->toIso8601String(),
            'expires_at' => $generation->expires_at?->toIso8601String(),
        ];
    }
}
