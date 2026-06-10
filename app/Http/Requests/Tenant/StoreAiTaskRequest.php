<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Terreno::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'terreno_id' => ['required', 'integer', 'exists:terrenos,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'concluded', 'cancelled'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_date' => ['nullable', 'date'],
            'related_type' => ['nullable', 'string'],
            'related_id' => ['nullable', 'integer'],
        ];
    }
}
