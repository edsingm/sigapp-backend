<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\CorretorExterno;
use App\Models\Tenant\User;
use App\Policies\Tenant\Concerns\ChecksTenantPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class CorretorExternoPolicy
{
    use HandlesAuthorization;
    use ChecksTenantPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view any corretores externos', 'view corretores externos');
    }

    public function view(User $user, CorretorExterno $model): bool
    {
        return $this->allows($user, 'view corretores externos');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create corretores externos');
    }

    public function update(User $user, CorretorExterno $model): bool
    {
        return $this->allows($user, 'update corretores externos');
    }

    public function delete(User $user, CorretorExterno $model): bool
    {
        return $this->allows($user, 'delete corretores externos');
    }

    public function restore(User $user, CorretorExterno $model): bool
    {
        return $this->allows($user, 'restore corretores externos');
    }
}
