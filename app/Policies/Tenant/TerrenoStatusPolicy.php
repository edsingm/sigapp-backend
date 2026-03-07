<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\TerrenoStatus;
use App\Models\Tenant\User;
use App\Policies\Tenant\Concerns\ChecksTenantPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class TerrenoStatusPolicy
{
    use HandlesAuthorization;
    use ChecksTenantPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view any terreno status', 'view terreno status');
    }

    public function view(User $user, TerrenoStatus $model): bool
    {
        return $this->allows($user, 'view terreno status');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create terreno status');
    }

    public function update(User $user, TerrenoStatus $model): bool
    {
        return $this->allows($user, 'update terreno status');
    }

    public function delete(User $user, TerrenoStatus $model): bool
    {
        return $this->allows($user, 'delete terreno status');
    }

    public function restore(User $user, TerrenoStatus $model): bool
    {
        return $this->allows($user, 'restore terreno status');
    }
}
