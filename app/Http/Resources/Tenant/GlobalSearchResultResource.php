<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GlobalSearchResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'type' => $this->resource['type'] ?? null,
            'title' => $this->resource['title'] ?? null,
            'subtitle' => $this->resource['subtitle'] ?? null,
            'description' => $this->resource['description'] ?? null,
            'href' => $this->resource['href'] ?? null,
            'icon' => $this->resource['icon'] ?? null,
            'score' => $this->resource['score'] ?? null,
            'highlights' => $this->resource['highlights'] ?? [],
            'entity' => $this->resource['entity'] ?? null,
            'data' => $this->resource['data'] ?? [],
        ];
    }
}
