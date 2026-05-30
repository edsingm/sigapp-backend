<?php

namespace App\Http\Resources;

use App\Models\Central\Cidade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cidade */
class CidadeBuscaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->resource->getAttribute('code'),
            'city' => $this->resource->getAttribute('city'),
            'state' => $this->resource->getAttribute('state'),
            'state_code' => $this->resource->getAttribute('state_code'),
        ];
    }
}
