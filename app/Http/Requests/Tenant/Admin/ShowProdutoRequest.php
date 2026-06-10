<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Admin;

use App\Models\Tenant\Produto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ShowProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Produto::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [];
    }
}
