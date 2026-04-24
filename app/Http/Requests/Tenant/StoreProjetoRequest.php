<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Projeto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreProjetoRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Projeto::class);
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'terreno_id' => ['required', 'integer', 'exists:terrenos,id'],
            'responsavel_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
