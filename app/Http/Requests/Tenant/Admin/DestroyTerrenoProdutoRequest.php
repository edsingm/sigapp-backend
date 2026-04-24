<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyTerrenoProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', \App\Models\Tenant\TerrenoProduto::class);
    }

    public function rules(): array
    {
        return [];
    }
}
