<?php

namespace App\Http\Requests\Admin;

use App\Enums\Common\EntitlementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntitlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    public function rules(): array
    {
        return [
            'key'           => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.]+$/', 'unique:entitlements,key'],
            'label'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:1000'],
            'type'          => ['required', Rule::enum(EntitlementType::class)],
            'default_value' => ['nullable'],
        ];
    }
}
