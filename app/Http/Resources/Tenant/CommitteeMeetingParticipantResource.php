<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ComiteMeetingParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommitteeMeetingParticipantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ComiteMeetingParticipant $participant */
        $participant = $this->resource;

        return [
            'id' => $participant->id,
            'session_id' => $participant->session_id,
            'user_id' => $participant->user_id,
            'name' => $participant->name ?? $participant->user?->name,
            'email' => $participant->email ?? $participant->user?->email,
            'role' => $participant->role,
            'attendance_status' => $participant->attendance_status,
            'joined_at' => $participant->joined_at?->toIso8601String(),
        ];
    }
}
