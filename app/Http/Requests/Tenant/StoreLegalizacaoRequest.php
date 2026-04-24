<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Legalizacao;
use Illuminate\Foundation\Http\FormRequest;

class StoreLegalizacaoRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Legalizacao::class);
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     */
    public function rules(): array
    {
        return [
            'terreno_id' => 'required|integer|exists:terrenos,id',
            'nome' => 'nullable|string|max:255',
            'responsavel_id' => 'nullable|integer|exists:users,id',
            'data_inicio_prevista' => 'nullable|date',
            'data_conclusao_prevista' => 'nullable|date|after_or_equal:data_inicio_prevista',
            'custo_total_previsto' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string|max:5000',
        ];
    }

    /**
     * Obtém as mensagens personalizadas para erros do validador.
     */
    public function messages(): array
    {
        return [
            'terreno_id.required' => 'O terreno é obrigatório.',
            'terreno_id.exists' => 'Terreno não encontrado.',
            'nome.max' => 'O nome não pode ter mais de 255 caracteres.',
            'responsavel_id.exists' => 'Responsável não encontrado.',
            'data_conclusao_prevista.after_or_equal' => 'A data de conclusão prevista deve ser posterior ou igual à data de início prevista.',
            'custo_total_previsto.numeric' => 'O custo total previsto deve ser um valor numérico.',
            'custo_total_previsto.min' => 'O custo total previsto deve ser maior ou igual a zero.',
            'observacoes.max' => 'As observações não podem ter mais de 5000 caracteres.',
        ];
    }
}
