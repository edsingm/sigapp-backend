<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Central\TenantAddonSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TenantAddonSubscription */
class TenantAddonSubscriptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'addon' => new TenantBillingAddonResource($this->whenLoaded('addon')),
            'quantity' => $this->quantity,
            'status' => $this->status->value,
            'grants_access' => $this->grantsAccess(),
            'cancel_at_period_end' => $this->cancel_at_period_end,
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
