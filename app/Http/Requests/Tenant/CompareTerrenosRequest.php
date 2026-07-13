<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Terreno;
use Illuminate\Foundation\Http\FormRequest;

class CompareTerrenosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Terreno::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'terreno_ids' => ['required', 'array', 'min:2', 'max:4'],
            'terreno_ids.*' => ['integer', 'distinct', 'exists:terrenos,id'],
        ];
    }
}
