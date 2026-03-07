<?php

namespace App\Policies\Tenant\Concerns;

use App\Models\Tenant\User;

trait ChecksTenantPermission
{
    protected function allows(User $user, string ...$permissions): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
