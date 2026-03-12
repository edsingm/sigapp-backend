<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContratoParteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'document' => $this->document,
            'party_type' => $this->party_type,
            'signer_name' => $this->signer_name,
            'signer_document' => $this->signer_document,
        ];
    }
}
