<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\Produto;
use App\Models\Tenant\User;
use App\Policies\Tenant\Concerns\ChecksTenantPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProdutoPolicy
{
    use HandlesAuthorization;
    use ChecksTenantPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view any produtos', 'view produtos');
    }

    public function view(User $user, Produto $model): bool
    {
        return $this->allows($user, 'view produtos');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create produtos');
    }

    public function update(User $user, Produto $model): bool
    {
        return $this->allows($user, 'update produtos');
    }

    public function delete(User $user, Produto $model): bool
    {
        return $this->allows($user, 'delete produtos');
    }

    public function restore(User $user, Produto $model): bool
    {
        return $this->allows($user, 'restore produtos');
    }
}
