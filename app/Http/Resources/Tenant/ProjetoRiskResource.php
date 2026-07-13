<?php

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\ProjetoRisk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjetoRiskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ProjetoRisk $risk */
        $risk = $this->resource;

        return [
            'id' => $risk->id,
            'projeto_id' => $risk->projeto_id,
            'title' => $risk->title,
            'description' => $risk->description,
            'probability' => $risk->probability,
            'impact' => $risk->impact,
            'severity' => $risk->severity,
            'status' => $risk->status,
            'mitigation' => $risk->mitigation,
            'responsible_id' => $risk->responsible_id,
            'responsible' => $this->whenLoaded('responsavel', fn () => [
                'id' => $risk->responsavel?->id,
                'name' => $risk->responsavel?->name,
                'email' => $risk->responsavel?->email,
            ]),
            'due_date' => $risk->due_date?->toDateString(),
            'created_at' => $risk->created_at?->toIso8601String(),
            'updated_at' => $risk->updated_at?->toIso8601String(),
        ];
    }
}
