<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Projeto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjetoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'responsavel_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'required', Rule::in([
                Projeto::STATUS_EM_VIABILIDADE,
                Projeto::STATUS_EM_LEGALIZACAO,
                Projeto::STATUS_PRONTO_PARA_REGISTRO,
                Projeto::STATUS_CANCELADO,
            ])],
        ];
    }
}
