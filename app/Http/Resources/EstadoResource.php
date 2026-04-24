<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EstadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'state_code' => $this->state_code,
            'state' => $this->state,
        ];
    }
}
