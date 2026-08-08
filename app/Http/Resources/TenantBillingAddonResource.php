<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Central\BillingAddon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BillingAddon */
class TenantBillingAddonResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $price = $this->priceDetails();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'currency' => $price['currency'],
            'billing_interval' => $price['interval'],
            'price_type' => $price['price_type'],
            'price' => [
                'unit_amount' => $price['unit_amount'],
                'currency' => $price['currency'],
                'interval' => $price['interval'],
                'type' => $price['price_type'],
            ],
            'formatted_price' => $price['formatted_price'],
            'is_purchasable' => $price['is_purchasable'],
            'purchased_quantity' => (int) ($this->resource->getAttribute('purchased_quantity') ?? 0),
            'ai_credit' => $this->resource->getAttribute('ai_credit_summary'),
            'definition' => $this->definition,
            'is_active' => $this->is_active,
        ];
    }

    /**
     * @return array{
     *     unit_amount: int|null,
     *     currency: string,
     *     interval: string,
     *     price_type: string,
     *     formatted_price: string|null,
     *     is_purchasable: bool
     * }
     */
    private function priceDetails(): array
    {
        $details = $this->resource instanceof BillingAddon
            ? $this->resource->getAttribute('price_details')
            : null;

        if (is_array($details)
            && (is_int($details['unit_amount'] ?? null) || $details['unit_amount'] === null)
            && is_string($details['currency'] ?? null)
            && is_string($details['interval'] ?? null)
            && (is_string($details['formatted_price'] ?? null) || $details['formatted_price'] === null)
            && is_bool($details['is_purchasable'] ?? null)
        ) {
            return [
                'unit_amount' => $details['unit_amount'],
                'currency' => $details['currency'],
                'interval' => $details['interval'],
                'price_type' => is_string($details['price_type'] ?? null)
                    ? $details['price_type']
                    : ($details['interval'] === 'one_time' ? 'one_time' : 'recurring'),
                'formatted_price' => $details['formatted_price'],
                'is_purchasable' => $details['is_purchasable'],
            ];
        }

        return [
            'unit_amount' => null,
            'currency' => strtolower((string) $this->currency),
            'interval' => (string) $this->billing_interval,
            'price_type' => $this->billing_interval === 'one_time' ? 'one_time' : 'recurring',
            'formatted_price' => null,
            'is_purchasable' => false,
        ];
    }
}
