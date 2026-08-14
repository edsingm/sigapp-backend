<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\User;
use Illuminate\Foundation\Http\FormRequest;

class StorePrivacyErasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return tenancy()->initialized && $user instanceof User;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => language()->t('PRIVACY_ERASURE_PASSWORD_REQUIRED'),
        ];
    }
}
