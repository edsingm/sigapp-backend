<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Viabilidade;
use Illuminate\Foundation\Http\FormRequest;

class CompareViabilidadesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('compare', Viabilidade::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'viabilidade_1_id' => ['required', 'integer', 'exists:viabilidades,id'],
            'viabilidade_2_id' => ['required', 'integer', 'exists:viabilidades,id'],
        ];
    }
}
