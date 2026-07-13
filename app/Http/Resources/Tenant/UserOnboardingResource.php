<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserOnboardingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'catalog_version' => $this->resource['catalog_version'],
            'profile' => $this->resource['profile'],
            'steps' => $this->resource['steps'],
            'completed_steps' => $this->resource['completed_steps'],
            'completed_count' => $this->resource['completed_count'],
            'total_count' => $this->resource['total_count'],
            'progress' => $this->resource['progress'],
            'dismissed' => $this->resource['dismissed'],
            'dismissed_at' => $this->resource['dismissed_at'],
            'resumed_at' => $this->resource['resumed_at'],
            'last_event_at' => $this->resource['last_event_at'],
        ];
    }
}
