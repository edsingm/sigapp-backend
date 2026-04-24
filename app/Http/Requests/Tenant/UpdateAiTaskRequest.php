<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Terreno::class) ?? false;
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'concluded', 'cancelled'])],
            'assigned_to' => ['nullable', 'integer'],
        ];
    }
}
