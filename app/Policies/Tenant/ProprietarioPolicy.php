<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\Proprietario;
use App\Models\Tenant\User;
use App\Policies\Tenant\Concerns\ChecksTenantPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProprietarioPolicy
{
    use HandlesAuthorization;
    use ChecksTenantPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view any proprietarios', 'view proprietarios');
    }

    public function view(User $user, Proprietario $model): bool
    {
        return $this->allows($user, 'view proprietarios');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create proprietarios');
    }

    public function update(User $user, Proprietario $model): bool
    {
        return $this->allows($user, 'update proprietarios');
    }

    public function delete(User $user, Proprietario $model): bool
    {
        return $this->allows($user, 'delete proprietarios');
    }

    public function restore(User $user, Proprietario $model): bool
    {
        return $this->allows($user, 'restore proprietarios');
    }
}
