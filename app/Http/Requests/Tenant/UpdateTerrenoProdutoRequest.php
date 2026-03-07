<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTerrenoProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'terreno_id' => 'sometimes|required|integer|exists:terrenos,id',
            'produto_id' => 'nullable|integer|exists:produtos,id',
            'unidades' => 'nullable|integer|min:0',
            'valor' => 'nullable|numeric|min:0',
            'permuta' => 'nullable|integer|min:0',
            'pgto_por_lote' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'terreno_id.required' => 'O terreno é obrigatório.',
            'terreno_id.exists' => 'Terreno inválido.',
            'produto_id.exists' => 'Produto inválido.',
            'unidades.integer' => 'O número de unidades deve ser um número inteiro.',
            'unidades.min' => 'O número de unidades não pode ser negativo.',
            'valor.numeric' => 'O valor deve ser um número.',
            'valor.min' => 'O valor não pode ser negativo.',
        ];
    }
}
