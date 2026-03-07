<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegionalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $regionalId = $this->route('regional');

        return [
            'nome' => 'sometimes|required|string|max:255|unique:regionais,nome,' . $regionalId,
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
