<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class DashboardStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_tenants' => $this['total_tenants'],
            'active_tenants' => $this['active_tenants'],
            'pending_tenants' => $this['pending_tenants'],
            'suspended_tenants' => $this['suspended_tenants'],
            'cancelled_tenants' => $this['cancelled_tenants'],
            'today_tenants' => $this['today_tenants'],
            'trial_tenants' => $this['trial_tenants'],
            'trial_expired_tenants' => $this['trial_expired_tenants'],
            'mrr' => $this['mrr'],
        ];
    }
}
