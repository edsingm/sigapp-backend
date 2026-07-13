<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ComiteMeetingMinutes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommitteeMinutesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ComiteMeetingMinutes $minutes */
        $minutes = $this->resource;

        return [
            'id' => $minutes->id,
            'session_id' => $minutes->session_id,
            'summary' => $minutes->summary,
            'decisions' => $minutes->decisions ?? [],
            'blockers' => $minutes->blockers ?? [],
            'next_steps' => $minutes->next_steps,
            'approved_by' => $minutes->approved_by,
            'approved_at' => $minutes->approved_at?->toIso8601String(),
        ];
    }
}
