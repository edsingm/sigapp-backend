<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Common\RolesEnum;
use App\Models\Tenant\User;
use Illuminate\Foundation\Http\FormRequest;

class AuthorizeTenantDirectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return tenancy()->initialized
            && $user instanceof User
            && $user->hasAnyRole([
                RolesEnum::ADMIN->value,
                RolesEnum::DIRECTOR->value,
            ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
