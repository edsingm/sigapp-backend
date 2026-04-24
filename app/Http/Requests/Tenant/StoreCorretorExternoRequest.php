<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\CorretorExterno;
use Illuminate\Foundation\Http\FormRequest;

class StoreCorretorExternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CorretorExterno::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return CorretorExterno::rules();
    }

    public function messages(): array
    {
        return CorretorExterno::messages();
    }
}
