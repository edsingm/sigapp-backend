<?php

namespace App\Http\Resources;

use App\Models\Central\Cidade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cidade */
class CidadeOpcaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $name = $this->resource->getAttribute('name');

        return [
            'code' => $this->resource->getAttribute('code'),
            'name' => is_string($name) && $name !== '' ? $name : $this->resource->getAttribute('city'),
        ];
    }
}
