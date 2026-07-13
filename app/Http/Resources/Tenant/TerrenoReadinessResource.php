<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerrenoReadinessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this['status'],
            'items' => $this['items'],
            'blocking_count' => $this['blocking_count'],
            'warning_count' => $this['warning_count'],
            'missing_data_count' => $this['missing_data_count'],
            'blocked_actions' => $this['blocked_actions'],
            'catalog_version' => $this['catalog_version'],
            'generated_at' => $this['generated_at'],
        ];
    }
}
