<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ShowTerrenoProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', \App\Models\Tenant\TerrenoProduto::class);
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [];
    }
}
