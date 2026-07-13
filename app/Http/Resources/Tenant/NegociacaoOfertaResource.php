<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\NegociacaoOferta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NegociacaoOfertaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var NegociacaoOferta $offer */
        $offer = $this->resource;

        return [
            'id' => $offer->id,
            'negociacao_id' => $offer->negociacao_id,
            'version' => $offer->version,
            'offer_type' => $offer->offer_type,
            'amount' => $offer->amount !== null ? (float) $offer->amount : null,
            'business_model' => $offer->business_model,
            'terms' => $offer->terms ?? [],
            'status' => $offer->status,
            'valid_until' => $offer->valid_until?->toDateString(),
            'previous_offer_id' => $offer->previous_offer_id,
            'created_by' => $offer->created_by,
            'accepted_at' => $offer->accepted_at?->toIso8601String(),
            'rejected_at' => $offer->rejected_at?->toIso8601String(),
        ];
    }
}
