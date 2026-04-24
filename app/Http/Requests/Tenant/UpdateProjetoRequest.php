<?php

namespace App\Http\Requests\Tenant;

use App\Enums\ProjetoStatus;
use App\Models\Tenant\Projeto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateProjetoRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', Projeto::class);
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'responsavel_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'required', Rule::in(ProjetoStatus::values())],
        ];
    }
}
