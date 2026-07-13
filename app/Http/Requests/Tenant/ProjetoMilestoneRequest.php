<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Projeto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ProjetoMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', Projeto::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', 'in:pending,in_progress,completed,blocked,cancelled'],
            'planned_start' => ['nullable', 'date'],
            'planned_end' => ['nullable', 'date', 'after_or_equal:planned_start'],
            'predicted_start' => ['nullable', 'date'],
            'predicted_end' => ['nullable', 'date', 'after_or_equal:predicted_start'],
            'actual_start' => ['nullable', 'date'],
            'actual_end' => ['nullable', 'date', 'after_or_equal:actual_start'],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'weight' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_critical' => ['sometimes', 'boolean'],
        ];
    }
}
