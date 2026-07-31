<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ReportSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReportSchedule */
class ReportScheduleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ReportSchedule $schedule */
        $schedule = $this->resource;

        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'template_id' => $schedule->report_template_id,
            'template_name' => $schedule->template?->name,
            'frequency' => $schedule->frequency,
            'format' => $schedule->format,
            'filters' => $schedule->filters,
            'notify_email' => $schedule->notify_email,
            'is_active' => $schedule->is_active,
            'next_run_at' => $schedule->next_run_at?->toIso8601String(),
            'last_run_at' => $schedule->last_run_at?->toIso8601String(),
            'last_run' => $schedule->relationLoaded('lastRun') && $schedule->lastRun
                ? [
                    'id' => $schedule->lastRun->id,
                    'status' => $schedule->lastRun->status,
                    'format' => $schedule->lastRun->format,
                    'completed_at' => $schedule->lastRun->completed_at?->toIso8601String(),
                ]
                : null,
            'created_at' => $schedule->created_at?->toIso8601String(),
            'updated_at' => $schedule->updated_at?->toIso8601String(),
        ];
    }
}
