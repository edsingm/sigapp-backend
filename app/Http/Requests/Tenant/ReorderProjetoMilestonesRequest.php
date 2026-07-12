<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Projeto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReorderProjetoMilestonesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', Projeto::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['milestone_ids' => ['required', 'array', 'min:1'], 'milestone_ids.*' => ['required', 'integer', 'distinct']];
    }
}
