<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
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
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'trial_days' => $this->trial_days,
            'features' => $this->features,
            'limits' => $this->limits,
            'is_active' => $this->is_active,
            'is_popular' => $this->is_popular,
            'sort_order' => $this->sort_order,
            'entitlements' => EntitlementResource::collection($this->whenLoaded('entitlements')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
