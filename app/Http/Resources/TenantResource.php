<?php

namespace App\Http\Resources;

use App\Services\Billing\TenantBillingProfileService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly ?Authenticatable $billingProfileUser = null)
    {
        parent::__construct($resource);
    }

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
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'on_trial' => $this->onTrial(),
            'is_active' => $this->isActive(),
            'setup_completed_at' => $this->setup_completed_at?->toIso8601String(),
            'billing_profile' => app(TenantBillingProfileService::class)
                ->summaryForUser($this->resource, $this->billingProfileUser ?? $request->user()),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
