<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CidadeBuscaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'city' => $this->city,
            'state' => $this->state,
            'state_code' => $this->state_code,
        ];
    }
}
