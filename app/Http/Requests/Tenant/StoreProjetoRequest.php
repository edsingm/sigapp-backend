<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjetoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'terreno_id' => ['required', 'integer', 'exists:terrenos,id'],
            'responsavel_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
