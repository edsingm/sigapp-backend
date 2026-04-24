<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\LegalizacaoEtapa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusEtapaRequest extends FormRequest
{
    private const VALID_STATUSES = [
        'pendente',
        'em_andamento',
        'concluida',
        'bloqueada',
        'atrasada',
        'nao_iniciada',
        'cancelada',
    ];

    public function authorize(): bool
    {
        $etapa = $this->route('id');

        if (! $etapa) {
            return false;
        }

        return $this->user()?->can('update', [LegalizacaoEtapa::class, $etapa]) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(self::VALID_STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'O status é obrigatório.',
            'status.string' => 'O status deve ser uma string.',
            'status.in' => 'Status inválido. Valores permitidos: pendente, em_andamento, concluida, bloqueada, atrasada, nao_iniciada, cancelada.',
        ];
    }
}
