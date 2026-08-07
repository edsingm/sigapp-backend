<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Central\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso exclusivo da administração central.
 * O preço Stripe é necessário para operações administrativas, mas não deve
 * fazer parte do contrato público/tenant de planos.
 */
/** @mixin Plan */
class AdminPlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'stripe_price_id' => $this->stripe_price_id,
            'trial_days' => $this->trial_days,
            'features' => $this->features,
            'limits' => $this->limits,
            'is_active' => $this->is_active,
            'is_popular' => $this->is_popular,
            'sort_order' => $this->sort_order,
            'entitlements' => EntitlementResource::collection($this->whenLoaded('entitlements')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
