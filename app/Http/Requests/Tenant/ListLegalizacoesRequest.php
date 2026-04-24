<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Legalizacao;
use Illuminate\Foundation\Http\FormRequest;

class ListLegalizacoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', Legalizacao::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'terreno_id' => ['nullable', 'integer', 'exists:terrenos,id'],
            'status' => ['nullable', 'in:planejado,em_andamento,concluido,cancelado'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
