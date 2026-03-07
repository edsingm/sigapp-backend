<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'trial_days' => $this->trial_days,
            'max_users' => $this->max_users,
            'max_storage_gb' => $this->max_storage_gb,
            'max_terrenos' => $this->max_terrenos,
            'features' => $this->features,
            'entitlements' => $this->entitlements,
            'feature_flags' => $this->feature_flags,
            'is_popular' => $this->is_popular,
            'unlimited_users' => $this->hasUnlimitedUsers(),
            'unlimited_terrenos' => $this->hasUnlimitedTerrenos(),
        ];
    }
}
