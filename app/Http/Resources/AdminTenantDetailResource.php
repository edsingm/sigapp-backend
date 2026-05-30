<?php

namespace App\Http\Resources;

use App\Models\Central\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminTenantDetailResource extends JsonResource
{
    /**
     * Transformar o recurso em um array.
     */
    public function toArray(Request $request): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->resource['tenant'];
        $trialEndsAt = $tenant->getAttribute('trial_ends_at');
        $setupCompletedAt = $tenant->getAttribute('setup_completed_at');

        return [
            'id' => $tenant->id,
            'name' => $tenant->getAttribute('name'),
            'slug' => $tenant->getAttribute('slug'),
            'status' => $tenant->getAttribute('status'),
            'admin_name' => $tenant->getAttribute('admin_name'),
            'admin_email' => $tenant->getAttribute('admin_email'),
            'stripe_id' => $tenant->getAttribute('stripe_id'),
            'stripe_subscription_id' => $tenant->getAttribute('stripe_subscription_id'),
            'database_created' => (bool) $tenant->getAttribute('database_created'),
            'trial_extended' => (bool) $tenant->getAttribute('trial_extended'),
            'trial_ends_at' => $trialEndsAt instanceof \DateTimeInterface ? $trialEndsAt->format(\DateTimeInterface::ATOM) : null,
            'on_trial' => $tenant->onTrial(),
            'trial_ended' => $tenant->trialEnded(),
            'setup_completed_at' => $setupCompletedAt instanceof \DateTimeInterface ? $setupCompletedAt->format(\DateTimeInterface::ATOM) : null,
            'created_at' => $tenant->created_at?->toIso8601String(),
            'updated_at' => $tenant->updated_at?->toIso8601String(),
            'plan' => new PlanResource($tenant->plan),
            'stats' => $this->resource['stats'],
            'finance' => $this->resource['finance'],
        ];
    }
}
