<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalizacaoRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:planejado,em_andamento,concluido,cancelado',
            'data_inicio_planejada' => 'sometimes|nullable|date',
            'data_fim_planejada' => 'sometimes|nullable|date|after:data_inicio_planejada',
            'data_inicio_real' => 'sometimes|nullable|date',
            'data_fim_real' => 'sometimes|nullable|date|after:data_inicio_real',
            'percentual_concluido' => 'sometimes|integer|min:0|max:100',
        ];
    }

    /**
     * Obtém as mensagens de erro para as regras de validação definidas.
     */
    public function messages(): array
    {
        return [
            'nome.string' => 'O nome deve ser um texto.',
            'status.in' => 'Status deve ser: planejado, em_andamento, concluido ou cancelado.',
            'data_inicio_planejada.date' => 'Data de início planejada inválida.',
            'data_fim_planejada.date' => 'Data de fim planejada inválida.',
            'data_fim_planejada.after' => 'Data de fim planejada deve ser posterior à data de início.',
            'data_inicio_real.date' => 'Data de início real inválida.',
            'data_fim_real.date' => 'Data de fim real inválida.',
            'data_fim_real.after' => 'Data de fim real deve ser posterior à data de início.',
            'percentual_concluido.integer' => 'Percentual deve ser um número inteiro.',
            'percentual_concluido.min' => 'Percentual não pode ser negativo.',
            'percentual_concluido.max' => 'Percentual não pode ser maior que 100.',
        ];
    }
}
