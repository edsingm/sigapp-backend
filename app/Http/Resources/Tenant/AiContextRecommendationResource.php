<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\AiContextRecommendation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiContextRecommendationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AiContextRecommendation $recommendation */
        $recommendation = $this->resource;

        return [
            'id' => $recommendation->id,
            'entity_type' => $recommendation->entity_type,
            'entity_id' => $recommendation->entity_id,
            'intent' => $recommendation->intent,
            'action' => $recommendation->action,
            'status' => $recommendation->status,
            'output' => $recommendation->output ?? [],
            'created_by' => $recommendation->created_by,
            'applied_by' => $recommendation->applied_by,
            'justification' => $recommendation->justification,
            'applied_at' => $recommendation->applied_at?->toIso8601String(),
            'expires_at' => $recommendation->expires_at?->toIso8601String(),
        ];
    }
}
