<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\User;
use App\Models\Tenant\Legalizacao;
use Illuminate\Auth\Access\HandlesAuthorization;

class LegalizacaoPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view any legalizacoes') || $user->isAdmin();
    }

    public function view(User $user, Legalizacao $legalizacao): bool
    {
        return $user->can('view legalizacoes') || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->can('create legalizacoes') || $user->isAdmin();
    }

    public function update(User $user, Legalizacao $legalizacao): bool
    {
        return $user->can('update legalizacoes') || $user->isAdmin();
    }

    public function delete(User $user, Legalizacao $legalizacao): bool
    {
        return $user->can('delete legalizacoes') || $user->isAdmin();
    }

    public function syncGantt(User $user, Legalizacao $legalizacao): bool
    {
        return $user->can('sync gantt legalizacoes') || $user->isAdmin();
    }

    public function recalcularProgresso(User $user, Legalizacao $legalizacao): bool
    {
        return $user->can('recalcular progresso legalizacoes') || $user->isAdmin();
    }
}
