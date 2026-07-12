<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Viabilidade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateViabilidadeScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Viabilidade::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'scenario_type' => ['sometimes', 'string', Rule::in(['base', 'optimistic', 'conservative', 'custom'])],
            'premises' => ['sometimes', 'array'],
        ];
    }
}
