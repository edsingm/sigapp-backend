<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\Documento;
use App\Models\Tenant\User;
use App\Policies\Tenant\Concerns\ChecksTenantPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentoPolicy
{
    use HandlesAuthorization;
    use ChecksTenantPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view any documentos', 'view documentos');
    }

    public function view(User $user, Documento $documento): bool
    {
        return $this->allows($user, 'view documentos');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create documentos');
    }

    public function update(User $user, Documento $documento): bool
    {
        return $this->allows($user, 'update documentos');
    }

    public function delete(User $user, Documento $documento): bool
    {
        return $this->allows($user, 'delete documentos');
    }
}
