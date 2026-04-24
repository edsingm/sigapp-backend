<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ListTerrenoProdutosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', \App\Models\Tenant\TerrenoProduto::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'terreno_id' => ['sometimes', 'integer', 'exists:terrenos,id'],
        ];
    }
}
