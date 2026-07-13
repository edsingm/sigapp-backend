<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ContextualAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'in:terreno'],
            'entity_id' => ['required', 'integer'],
            'intent' => ['required', 'string', 'in:score,readiness,workflow'],
            'parameters' => ['nullable', 'array'],
            'action' => ['nullable', 'string', 'in:create_task'],
        ];
    }
}
