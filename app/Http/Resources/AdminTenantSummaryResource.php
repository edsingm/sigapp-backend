<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminTenantSummaryResource extends JsonResource
{
    /**
     * Transformar o recurso em um array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'admin_name' => $this->admin_name,
            'admin_email' => $this->admin_email,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'on_trial' => $this->onTrial(),
            'trial_ended' => $this->trialEnded(),
            'database_created' => (bool) $this->database_created,
            'setup_completed_at' => $this->setup_completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
