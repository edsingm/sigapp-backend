<?php

namespace App\Http\Resources;

use App\Models\Central\Cidade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cidade */
class EstadoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'state_code' => $this->resource->getAttribute('state_code'),
            'state' => $this->resource->getAttribute('state'),
        ];
    }
}
