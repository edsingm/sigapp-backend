<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreMobileDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        $tenantId = $this->input('tenant_id');

        return $tenantId === null || (string) $tenantId === (string) tenant('id');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'installation_id' => ['required', 'string', 'max:255'],
            'expo_push_token' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:64'],
            'platform' => ['required', 'string', 'max:32'],
            'tenant_id' => ['nullable', 'string'],
        ];
    }
}
