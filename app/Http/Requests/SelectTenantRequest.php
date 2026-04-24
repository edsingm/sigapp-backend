<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelectTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'broker_session_id' => ['required', 'string'],
            'tenant_id' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
