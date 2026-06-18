<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Regional;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreRegionalRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Regional::class);
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255|unique:regionais,nome',
            'email' => 'nullable|string|email|max:255|unique:regionais,email',
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
