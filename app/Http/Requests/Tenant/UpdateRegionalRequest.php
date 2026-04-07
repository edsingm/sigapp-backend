<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRegionalRequest extends FormRequest
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
        $regionalId = $this->route('regionai');

        return [
            'nome' => 'sometimes|required|string|max:255|unique:regionais,nome,'.$regionalId,
            'estado' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:50',
            'telefone' => 'nullable|string|max:50',
            'celular' => 'nullable|string|max:50',
            'observacoes' => 'nullable|string',
            'responsavel_id' => 'nullable|exists:users,id',
        ];
    }
}
