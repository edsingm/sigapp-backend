<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ContratoCondicao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContratoCondicaoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ContratoCondicao $condition */
        $condition = $this->resource;

        return [
            'id' => $condition->id,
            'contrato_id' => $condition->contrato_id,
            'title' => $condition->title,
            'description' => $condition->description,
            'responsible_id' => $condition->responsible_id,
            'due_date' => $condition->due_date?->toDateString(),
            'status' => $condition->status,
            'evidence_document_id' => $condition->evidence_document_id,
            'fulfilled_at' => $condition->fulfilled_at?->toIso8601String(),
        ];
    }
}
