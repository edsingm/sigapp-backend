<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\User;
use App\Models\Tenant\LegalizacaoEtapa;
use Illuminate\Auth\Access\HandlesAuthorization;

class LegalizacaoEtapaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view any legalizacao etapas') || $user->isAdmin();
    }

    public function view(User $user, LegalizacaoEtapa $etapa): bool
    {
        return $user->can('view legalizacao etapas') || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->can('create legalizacao etapas') || $user->isAdmin();
    }

    public function update(User $user, LegalizacaoEtapa $etapa): bool
    {
        return $user->can('update legalizacao etapas') || $user->isAdmin();
    }

    public function delete(User $user, LegalizacaoEtapa $etapa): bool
    {
        return $user->can('delete legalizacao etapas') || $user->isAdmin();
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder legalizacao etapas') || $user->isAdmin();
    }
}
