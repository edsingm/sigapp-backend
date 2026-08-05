<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Common\RolesEnum;
use App\Models\Tenant\User;
use Illuminate\Foundation\Http\FormRequest;

class ShowTenantBillingProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return tenancy()->initialized
            && $user instanceof User
            && $user->hasRole(RolesEnum::ADMIN->value);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }
}
