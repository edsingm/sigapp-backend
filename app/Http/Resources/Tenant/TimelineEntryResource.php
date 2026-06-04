<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimelineEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'type' => $this->resource['type'],
            'timestamp' => $this->resource['timestamp'],
            'user_id' => $this->resource['user_id'],
            'user_name' => $this->resource['user_name'],
            'summary' => $this->resource['summary'],
            'data' => $this->resource['data'],
        ];
    }
}
