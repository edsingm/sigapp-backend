<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ReportRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReportRun */
class ReportRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ReportRun $run */
        $run = $this->resource;

        return [
            'id' => $run->id,
            'template_id' => $run->report_template_id,
            'template_name' => $run->template?->name,
            'status' => $run->status,
            'progress' => $run->progress,
            'format' => $run->format,
            'filters' => $run->filters,
            'mime_type' => $run->mime_type,
            'size' => $run->size,
            'error_message' => $run->status === 'failed' ? $run->error_message : null,
            'download_url' => $run->status === 'completed' ? url("/api/v1/reports/runs/{$run->id}/download") : null,
            'requested_at' => $run->requested_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'expires_at' => $run->expires_at?->toIso8601String(),
        ];
    }
}
