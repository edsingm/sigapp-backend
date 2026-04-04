<?php

namespace App\Http\Requests\Admin;

use App\Enums\Common\EntitlementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntitlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    public function rules(): array
    {
        $entitlementId = (int) $this->route('entitlement');

        return [
            'key'           => ['sometimes', 'string', 'max:100', 'regex:/^[a-z0-9_.]+$/', Rule::unique('entitlements', 'key')->ignore($entitlementId)],
            'label'         => ['sometimes', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:1000'],
            'type'          => ['sometimes', Rule::enum(EntitlementType::class)],
            'default_value' => ['nullable'],
        ];
    }
}
