<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use App\Policies\Tenant\Concerns\ChecksTenantPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class ViabilidadePolicy
{
    use HandlesAuthorization;
    use ChecksTenantPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view any viabilidades', 'view viabilidades');
    }

    public function view(User $user, Viabilidade $viabilidade): bool
    {
        return $this->allows($user, 'view viabilidades');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create viabilidades');
    }

    public function update(User $user, Viabilidade $viabilidade): bool
    {
        return $this->allows($user, 'update viabilidades');
    }

    public function delete(User $user, Viabilidade $viabilidade): bool
    {
        return $this->allows($user, 'delete viabilidades');
    }

    public function restore(User $user, Viabilidade $viabilidade): bool
    {
        return $this->allows($user, 'restore viabilidades');
    }

    public function ativar(User $user, Viabilidade $viabilidade): bool
    {
        return $this->allows($user, 'activate viabilidades', 'update viabilidades');
    }

    public function requestApproval(User $user, Viabilidade $viabilidade): bool
    {
        return $this->allows($user, 'request approval viabilidades', 'update viabilidades');
    }

    public function approve(User $user, Viabilidade $viabilidade): bool
    {
        return $this->allows($user, 'approve viabilidades', 'update viabilidades');
    }

    public function duplicate(User $user, Viabilidade $viabilidade): bool
    {
        return $this->allows($user, 'duplicate viabilidades', 'create viabilidades');
    }

    public function compare(User $user, mixed $ignored = null): bool
    {
        return $this->allows($user, 'compare viabilidades', 'view viabilidades');
    }

    public function gerarDre(User $user, Viabilidade $viabilidade): bool
    {
        return $this->allows($user, 'generate dre viabilidades', 'view viabilidades');
    }

    public function recalcular(User $user, Viabilidade $viabilidade): bool
    {
        return $this->allows($user, 'recalculate viabilidades', 'update viabilidades');
    }

    public function export(User $user, ?Viabilidade $viabilidade = null): bool
    {
        return $this->allows($user, 'export viabilidades', 'view viabilidades');
    }
}
