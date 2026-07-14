<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Enums\AiReportGenerationStatus;
use App\Models\Tenant\AiReportGeneration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiReportGeneration */
class AiReportGenerationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AiReportGeneration $generation */
        $generation = $this->resource;
        $status = $generation->status;

        return [
            'id' => $generation->id,
            'terreno_id' => $generation->terreno_id,
            'status' => $status->value,
            'progress' => $generation->progress,
            'report_id' => $generation->report_id,
            'download_url' => $status === AiReportGenerationStatus::COMPLETED && $generation->report_id
                ? route('ai.reports.download', ['id' => $generation->report_id])
                : null,
            'error_message' => $status === AiReportGenerationStatus::FAILED
                ? $generation->error_message
                : null,
            'requested_at' => $generation->requested_at?->toIso8601String(),
            'started_at' => $generation->started_at?->toIso8601String(),
            'completed_at' => $generation->completed_at?->toIso8601String(),
        ];
    }
}
