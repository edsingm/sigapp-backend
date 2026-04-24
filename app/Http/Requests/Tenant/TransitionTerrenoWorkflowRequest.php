<?php

namespace App\Http\Requests\Tenant;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionTerrenoWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $terrenoId = $this->route('terreno') ?? $this->route('id');
        $terreno = $terrenoId instanceof Terreno ? $terrenoId : Terreno::find($terrenoId);

        return $user !== null
            && $terreno instanceof Terreno
            && $user->can('update', $terreno);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_status' => ['required', Rule::enum(WorkflowStatus::class)],
            'reason_code' => ['nullable', 'string', 'max:255'],
            'reason_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
