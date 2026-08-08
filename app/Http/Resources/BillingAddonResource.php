<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Central\BillingAddon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BillingAddon */
class BillingAddonResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'stripe_price_id' => $this->stripe_price_id,
            'currency' => $this->currency,
            'billing_interval' => $this->billing_interval,
            'definition' => $this->definition,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
