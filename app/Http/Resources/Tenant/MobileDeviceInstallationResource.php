<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\MobileDeviceInstallation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MobileDeviceInstallation */
class MobileDeviceInstallationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getAttribute('id'),
            'installation_id' => $this->resource->getAttribute('installation_id'),
            'platform' => $this->resource->getAttribute('platform'),
            'device_name' => $this->resource->getAttribute('device_name'),
            'app_version' => $this->resource->getAttribute('app_version'),
            'expo_push_token' => $this->resource->getAttribute('expo_push_token'),
            'last_seen_at' => $this->dateTime('last_seen_at'),
            'created_at' => $this->dateTime('created_at'),
            'updated_at' => $this->dateTime('updated_at'),
        ];
    }

    private function dateTime(string $key): ?string
    {
        $value = $this->resource->getAttribute($key);

        return $value instanceof \DateTimeInterface ? $value->format(\DateTimeInterface::ATOM) : null;
    }
}
