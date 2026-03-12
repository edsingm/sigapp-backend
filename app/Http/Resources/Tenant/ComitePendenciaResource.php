<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComitePendenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity,
            'status' => $this->status,
            'department_code' => $this->department_code,
            'responsible_user_id' => $this->responsible_user_id,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
