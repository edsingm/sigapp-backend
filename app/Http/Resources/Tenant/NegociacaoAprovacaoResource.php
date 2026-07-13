<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\NegociacaoAprovacao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NegociacaoAprovacaoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var NegociacaoAprovacao $approval */
        $approval = $this->resource;

        return [
            'id' => $approval->id,
            'negociacao_id' => $approval->negociacao_id,
            'area' => $approval->area,
            'decision' => $approval->decision,
            'comment' => $approval->comment,
            'decided_by' => $approval->decided_by,
            'decided_at' => $approval->decided_at?->toIso8601String(),
        ];
    }
}
