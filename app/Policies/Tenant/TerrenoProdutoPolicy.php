<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\User;
use App\Policies\Tenant\Concerns\ChecksTenantPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class TerrenoProdutoPolicy
{
    use HandlesAuthorization;
    use ChecksTenantPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view any terreno produtos', 'view terreno produtos');
    }

    public function view(User $user, TerrenoProduto $model): bool
    {
        return $this->allows($user, 'view terreno produtos');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create terreno produtos');
    }

    public function update(User $user, TerrenoProduto $model): bool
    {
        return $this->allows($user, 'update terreno produtos');
    }

    public function delete(User $user, TerrenoProduto $model): bool
    {
        return $this->allows($user, 'delete terreno produtos');
    }

    public function restore(User $user, TerrenoProduto $model): bool
    {
        return $this->allows($user, 'restore terreno produtos');
    }
}
