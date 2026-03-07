<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\Projeto;
use App\Models\Tenant\User;
use App\Policies\Tenant\Concerns\ChecksTenantPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjetoPolicy
{
    use HandlesAuthorization;
    use ChecksTenantPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view any projetos', 'view projetos', 'view any terrenos', 'view terrenos');
    }

    public function view(User $user, Projeto $projeto): bool
    {
        return $this->allows($user, 'view projetos', 'view terrenos');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create projetos', 'create terrenos', 'update terrenos');
    }

    public function update(User $user, Projeto $projeto): bool
    {
        return $this->allows($user, 'update projetos', 'update terrenos');
    }

    public function cancel(User $user, Projeto $projeto): bool
    {
        return $this->allows($user, 'cancel projetos', 'update projetos', 'update terrenos');
    }

    public function markReady(User $user, Projeto $projeto): bool
    {
        return $this->allows($user, 'mark ready projetos', 'update projetos', 'update terrenos');
    }
}
