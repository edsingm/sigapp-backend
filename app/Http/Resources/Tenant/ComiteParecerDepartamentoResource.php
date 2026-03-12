<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComiteParecerDepartamentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department_code' => $this->department_code,
            'reviewer_user_id' => $this->reviewer_user_id,
            'decision' => $this->decision,
            'comments' => $this->comments,
            'checklist_completed' => $this->checklist_completed,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
