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

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'admin_name' => $tenant->admin_name,
            'admin_email' => $tenant->admin_email,
            'stripe_id' => $tenant->stripe_id,
            'stripe_subscription_id' => $tenant->stripe_subscription_id,
            'database_created' => (bool) $tenant->database_created,
            'trial_extended' => (bool) $tenant->trial_extended,
            'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
            'on_trial' => $tenant->onTrial(),
            'trial_ended' => $tenant->trialEnded(),
            'setup_completed_at' => $tenant->setup_completed_at?->toIso8601String(),
            'created_at' => $tenant->created_at?->toIso8601String(),
            'updated_at' => $tenant->updated_at?->toIso8601String(),
            'plan' => new PlanResource($tenant->plan),
            'stats' => $this->resource['stats'],
            'finance' => $this->resource['finance'],
        ];
    }
}
