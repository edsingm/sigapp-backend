<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\Regional;
use App\Models\Tenant\User;
use App\Policies\Tenant\Concerns\ChecksTenantPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class RegionalPolicy
{
    use HandlesAuthorization;
    use ChecksTenantPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view any regionais', 'view regionais');
    }

    public function view(User $user, Regional $model): bool
    {
        return $this->allows($user, 'view regionais');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create regionais');
    }

    public function update(User $user, Regional $model): bool
    {
        return $this->allows($user, 'update regionais');
    }

    public function delete(User $user, Regional $model): bool
    {
        return $this->allows($user, 'delete regionais');
    }

    public function restore(User $user, Regional $model): bool
    {
        return $this->allows($user, 'restore regionais');
    }
}
