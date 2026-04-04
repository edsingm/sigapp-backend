<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SyncPlanEntitlementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    public function rules(): array
    {
        return [
            'entitlements'                  => ['required', 'array'],
            'entitlements.*.entitlement_id' => ['required', 'integer', 'exists:entitlements,id'],
            'entitlements.*.value'          => ['required'],
        ];
    }
}
