<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Admin;

use App\Models\Tenant\Regional;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SelectRegionaisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Regional::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [];
    }
}
