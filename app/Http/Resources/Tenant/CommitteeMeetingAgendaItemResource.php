<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ComiteMeetingAgendaItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommitteeMeetingAgendaItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ComiteMeetingAgendaItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'session_id' => $item->session_id,
            'title' => $item->title,
            'description' => $item->description,
            'position' => $item->position,
            'duration_minutes' => $item->duration_minutes,
            'decision_required' => $item->decision_required,
            'status' => $item->status,
        ];
    }
}
