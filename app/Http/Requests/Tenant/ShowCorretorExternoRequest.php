<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\CorretorExterno;
use Illuminate\Foundation\Http\FormRequest;

class ShowCorretorExternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', CorretorExterno::class) ?? false;
    }
}
