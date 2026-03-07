<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Policies\Tenant\Concerns\ChecksTenantPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class TerrenoPolicy
{
    use HandlesAuthorization;
    use ChecksTenantPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view any terrenos', 'view terrenos');
    }

    public function view(User $user, Terreno $terreno): bool
    {
        return $this->allows($user, 'view terrenos');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create terrenos');
    }

    public function update(User $user, Terreno $terreno): bool
    {
        return $this->allows($user, 'update terrenos');
    }

    public function delete(User $user, Terreno $terreno): bool
    {
        return $this->allows($user, 'delete terrenos');
    }

    public function restore(User $user, Terreno $terreno): bool
    {
        return $this->allows($user, 'restore terrenos');
    }

    public function forceDelete(User $user, Terreno $terreno): bool
    {
        return $this->allows($user, 'delete terrenos');
    }

    public function export(User $user, ?Terreno $terreno = null): bool
    {
        return $this->allows($user, 'export terrenos');
    }
}
