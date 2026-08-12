<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Central\HiperdadosImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HiperdadosImport
 */
class HiperdadosImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var HiperdadosImport $import */
        $import = $this->resource;

        $summary = is_array($import->summary) ? $import->summary : [];
        // Nunca expor lista completa de falhas sensíveis em excesso; cap 50.
        if (isset($summary['failures']) && is_array($summary['failures'])) {
            $summary['failures'] = array_slice($summary['failures'], 0, 50);
        }

        return [
            'id' => $import->uuid,
            'status' => $import->status->value,
            'portal_username' => $import->portal_username,
            'tenant_id' => $import->tenant_id,
            'tenant' => $import->relationLoaded('tenant') && $import->tenant !== null
                ? [
                    'id' => $import->tenant->id,
                    'name' => $import->tenant->name,
                    'slug' => $import->tenant->slug,
                ]
                : null,
            'limit' => $import->limit_count,
            'total_count' => $import->total_count,
            'processed_count' => $import->processed_count,
            'failed_count' => $import->failed_count,
            'imported_count' => $import->imported_count,
            'error_message' => $import->error_message,
            'summary' => $summary,
            'can_commit' => $import->status->canCommit(),
            'has_payload' => is_string($import->storage_path) && $import->storage_path !== '',
            'started_at' => $import->started_at?->toIso8601String(),
            'finished_at' => $import->finished_at?->toIso8601String(),
            'created_at' => $import->created_at?->toIso8601String(),
            'updated_at' => $import->updated_at?->toIso8601String(),
            'created_by' => $import->relationLoaded('creator') && $import->creator !== null
                ? [
                    'id' => $import->creator->id,
                    'name' => $import->creator->name,
                    'email' => $import->creator->email,
                ]
                : null,
        ];
    }
}
