<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantEntitlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entitlement_id' => $this->entitlement_id,
            'entitlement' => new EntitlementResource($this->whenLoaded('entitlement')),
            'value' => $this->value,
            'price' => $this->price,
            'price_formatted' => 'R$ '.number_format($this->price / 100, 2, ',', '.'),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
