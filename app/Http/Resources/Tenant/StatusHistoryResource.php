<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_stage' => $this->old_stage,
            'old_status_code' => $this->old_status_code,
            'new_stage' => $this->new_stage,
            'new_status_code' => $this->new_status_code,
            'reason_code' => $this->reason_code,
            'reason' => $this->reason,
            'metadata' => $this->metadata_json,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
