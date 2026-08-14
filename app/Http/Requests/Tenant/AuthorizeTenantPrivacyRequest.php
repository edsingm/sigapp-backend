<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\User;
use Illuminate\Foundation\Http\FormRequest;

class AuthorizeTenantPrivacyRequest extends FormRequest
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
        return [];
    }
}
