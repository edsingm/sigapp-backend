<?php

namespace App\Http\Resources;

use App\Models\Central\Cidade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cidade */
class CidadeDadosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getAttribute('id'),
            'code' => $this->resource->getAttribute('code'),
            'city' => $this->resource->getAttribute('city'),
            'state' => $this->resource->getAttribute('state'),
            'state_code' => $this->resource->getAttribute('state_code'),
            'latitude' => $this->resource->getAttribute('latitude'),
            'longitude' => $this->resource->getAttribute('longitude'),
            'capital' => $this->resource->getAttribute('capital'),
            'area_code' => $this->resource->getAttribute('area_code'),
            'timezone' => $this->resource->getAttribute('timezone'),
            'population' => $this->resource->getAttribute('population'),
            'employed' => $this->resource->getAttribute('employed'),
            'per_capta_income' => $this->resource->getAttribute('per_capta_income'),
            'property_maximum_value' => $this->resource->getAttribute('property_maximum_value'),
            'buyer_demand' => $this->resource->getAttribute('buyer_demand'),
            'own_property' => $this->resource->getAttribute('own_property'),
            'rented_property' => $this->resource->getAttribute('rented_property'),
        ];
    }
}
