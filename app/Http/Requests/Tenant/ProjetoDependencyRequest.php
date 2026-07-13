<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Projeto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ProjetoDependencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', Projeto::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'predecessor_milestone_id' => ['required', 'integer', 'different:successor_milestone_id'],
            'successor_milestone_id' => ['required', 'integer'],
            'dependency_type' => ['sometimes', 'string', 'in:finish_to_start,start_to_start,finish_to_finish,start_to_finish'],
            'lag_days' => ['sometimes', 'integer', 'min:-3650', 'max:3650'],
        ];
    }
}
