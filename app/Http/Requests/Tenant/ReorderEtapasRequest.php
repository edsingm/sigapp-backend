<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\LegalizacaoEtapa;
use Illuminate\Foundation\Http\FormRequest;

class ReorderEtapasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reorder', LegalizacaoEtapa::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'etapas' => ['required', 'array', 'min:1'],
            'etapas.*.id' => ['required', 'integer', 'exists:legalizacao_etapas,id'],
            'etapas.*.ordem' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'etapas.required' => 'A lista de etapas é obrigatória.',
            'etapas.array' => 'A lista de etapas deve ser um array.',
            'etapas.min' => 'É necessário informar pelo menos uma etapa.',
            'etapas.*.id.required' => 'O ID da etapa é obrigatório.',
            'etapas.*.id.integer' => 'O ID da etapa deve ser um número inteiro.',
            'etapas.*.id.exists' => 'Etapa não encontrada.',
            'etapas.*.ordem.required' => 'A ordem da etapa é obrigatória.',
            'etapas.*.ordem.integer' => 'A ordem deve ser um número inteiro.',
            'etapas.*.ordem.min' => 'A ordem deve ser maior ou igual a 1.',
        ];
    }
}
