<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;

class TransitionAiWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Terreno::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'terreno_id' => ['required', 'integer', 'exists:terrenos,id'],
            'target_status' => ['required', 'string'],
            'reason_code' => ['nullable', 'string'],
            'reason_notes' => ['nullable', 'string'],
        ];
    }
}
