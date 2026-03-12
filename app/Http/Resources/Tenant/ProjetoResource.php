<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\Tenant\LandWorkflowService;

class ProjetoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'terreno_id' => $this->terreno_id,
            'responsavel_id' => $this->responsavel_id,
            'status' => $this->status,
            'pronto_para_registro_em' => $this->pronto_para_registro_em?->toIso8601String(),
            'responsavel' => $this->whenLoaded('responsavel', fn () => [
                'id' => $this->responsavel->id,
                'name' => $this->responsavel->name,
                'email' => $this->responsavel->email,
            ]),
            'terreno' => $this->whenLoaded('terreno', fn () => [
                'id' => $this->terreno->id,
                'nome' => $this->terreno->nome,
                'status' => LandWorkflowService::statuses()[$this->terreno->workflow_status_code]['label'] ?? null,
            ]),
            'pronto_para_registro_por_user' => $this->whenLoaded('prontoParaRegistroPor', fn () => [
                'id' => $this->prontoParaRegistroPor->id,
                'name' => $this->prontoParaRegistroPor->name,
                'email' => $this->prontoParaRegistroPor->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
