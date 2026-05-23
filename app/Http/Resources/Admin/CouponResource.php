<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stripe_coupon_id' => $this->stripe_coupon_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'amount_off' => $this->amount_off,
            'percent_off' => $this->percent_off,
            'currency' => $this->currency,
            'max_redemptions' => $this->max_redemptions,
            'times_redeemed' => $this->times_redeemed,
            'redeem_by' => $this->redeem_by?->toIso8601String(),
            'expires_after_first_redemption' => $this->expires_after_first_redemption,
            'is_active' => $this->is_active,
            'applies_to_plans' => $this->applies_to_plans,
            'applies_to_tenants' => $this->applies_to_tenants,
            'formatted_discount' => $this->formatted_discount,
            'is_available' => $this->isAvailable(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
