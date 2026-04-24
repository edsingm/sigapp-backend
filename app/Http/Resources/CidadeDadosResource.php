<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CidadeDadosResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'city' => $this->city,
            'state' => $this->state,
            'state_code' => $this->state_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'capital' => $this->capital,
            'area_code' => $this->area_code,
            'timezone' => $this->timezone,
            'population' => $this->population,
            'employed' => $this->employed,
            'per_capta_income' => $this->per_capta_income,
            'property_maximum_value' => $this->property_maximum_value,
            'buyer_demand' => $this->buyer_demand,
            'own_property' => $this->own_property,
            'rented_property' => $this->rented_property,
        ];
    }
}
