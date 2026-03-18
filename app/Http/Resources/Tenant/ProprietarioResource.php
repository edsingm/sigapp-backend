<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProprietarioResource extends JsonResource
{
    /**
     * Transformar o recurso em um array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terreno_id' => $this->terreno_id,
            'nome' => $this->nome,
            'rg' => $this->rg,
            'cpf_cnpj' => $this->cpf_cnpj,
            'nascimento' => $this->nascimento?->format('Y-m-d'),
            'tipo_pessoa' => $this->tipo_pessoa,
            'estado_civil' => $this->estado_civil,
            'nacionalidade' => $this->nacionalidade,
            'profissao' => $this->profissao,
            'porcentagem_terreno' => $this->porcentagem_terreno ? (float) $this->porcentagem_terreno : null,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'endereco' => $this->endereco,
            'cidade' => $this->cidade,
            'estado' => $this->estado,
            'cep' => $this->cep,
            'conjuge' => $this->conjuge,
            'conjuge_rg' => $this->conjuge_rg,
            'conjuge_nascimento' => $this->conjuge_nascimento?->format('Y-m-d'),
            'conjuge_cpf_cnpj' => $this->conjuge_cpf_cnpj,
            'observacoes' => $this->observacoes,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'cpf_cnpj_formatado' => $this->cpf_cnpj_formatado,
            'conjuge_cpf_cnpj_formatado' => $this->conjuge_cpf_cnpj_formatado,
            'telefone_formatado' => $this->telefone_formatado,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
