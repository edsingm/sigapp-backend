<?php

namespace App\Http\Resources\Admin;

use App\Models\Central\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Coupon */
class CouponResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $redeemBy = $this->resource->getAttribute('redeem_by');
        $createdAt = $this->resource->getAttribute('created_at');
        $updatedAt = $this->resource->getAttribute('updated_at');

        return [
            'id' => $this->resource->getAttribute('id'),
            'stripe_coupon_id' => $this->resource->getAttribute('stripe_coupon_id'),
            'code' => $this->resource->getAttribute('code'),
            'name' => $this->resource->getAttribute('name'),
            'description' => $this->resource->getAttribute('description'),
            'type' => $this->resource->getAttribute('type'),
            'amount_off' => $this->resource->getAttribute('amount_off'),
            'percent_off' => $this->resource->getAttribute('percent_off'),
            'currency' => $this->resource->getAttribute('currency'),
            'max_redemptions' => $this->resource->getAttribute('max_redemptions'),
            'times_redeemed' => $this->resource->getAttribute('times_redeemed'),
            'redeem_by' => $redeemBy instanceof \DateTimeInterface ? $redeemBy->format(\DateTimeInterface::ATOM) : null,
            'expires_after_first_redemption' => $this->resource->getAttribute('expires_after_first_redemption'),
            'is_active' => $this->resource->getAttribute('is_active'),
            'applies_to_plans' => $this->resource->getAttribute('applies_to_plans'),
            'applies_to_tenants' => $this->resource->getAttribute('applies_to_tenants'),
            'formatted_discount' => $this->resource->formattedDiscount(),
            'is_available' => $this->resource->isAvailable(),
            'created_at' => $createdAt instanceof \DateTimeInterface ? $createdAt->format(\DateTimeInterface::ATOM) : null,
            'updated_at' => $updatedAt instanceof \DateTimeInterface ? $updatedAt->format(\DateTimeInterface::ATOM) : null,
        ];
    }
}
