<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ComiteMeetingSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommitteeMeetingSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ComiteMeetingSession $session */
        $session = $this->resource;

        return [
            'id' => $session->id,
            'comite_revisao_id' => $session->comite_revisao_id,
            'title' => $session->title,
            'scheduled_at' => $session->scheduled_at?->toIso8601String(),
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'status' => $session->status,
            'meeting_mode' => $session->meeting_mode,
            'location' => $session->location,
            'chair_user_id' => $session->chair_user_id,
            'chair' => $this->whenLoaded('chair', fn () => [
                'id' => $session->chair?->id,
                'name' => $session->chair?->name,
                'email' => $session->chair?->email,
            ]),
            'notes' => $session->notes,
            'agenda' => CommitteeMeetingAgendaItemResource::collection($this->whenLoaded('agendaItems')),
            'participants' => CommitteeMeetingParticipantResource::collection($this->whenLoaded('participants')),
            'minutes' => $this->whenLoaded('minutes', fn () => $session->minutes ? new CommitteeMinutesResource($session->minutes) : null),
            'created_at' => $session->created_at?->toIso8601String(),
            'updated_at' => $session->updated_at?->toIso8601String(),
        ];
    }
}
