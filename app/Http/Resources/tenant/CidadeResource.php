<?php

namespace App\Http\Resources\tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CidadeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
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

            // Campos formatados
            'formatted_population' => $this->formatted_population,
            'formatted_per_capta_income' => $this->formatted_per_capta_income,
            'has_coordinates' => $this->has_coordinates,

            // Informações adicionais
            'full_location' => $this->city . ', ' . $this->state_code,
            'display_name' => $this->city . ' - ' . $this->state,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}
