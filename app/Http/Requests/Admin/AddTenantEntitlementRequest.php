<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AddTenantEntitlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    public function rules(): array
    {
        return [
            'entitlement_id' => ['required', 'integer', 'exists:entitlements,id'],
            'value'          => ['required'],
            'price'          => ['required', 'integer', 'min:0'],
        ];
    }
}
