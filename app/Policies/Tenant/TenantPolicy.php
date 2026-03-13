<?php

namespace App\Policies\Tenant;

use App\Services\Acl\PermissionNameResolver;
use App\Models\Tenant\User;

/**
 * Single policy for all tenant models.
 *
 * All logic lives in before(), which Laravel calls with the model class (for
 * viewAny/create) or model instance (for view/update/delete/etc.) before the
 * named policy method below.
 */
class TenantPolicy
{
    public function before(User $user, string $ability, mixed $modelOrClass = null): ?bool
    {
        $permission = app(PermissionNameResolver::class)->forModel($modelOrClass, $ability);

        if (!$permission) {
            return null;
        }

        return app(PermissionNameResolver::class)->userCan($user, $permission);
    }

    public function viewAny(User $user): bool { return false; }
    public function view(User $user, mixed $model): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, mixed $model): bool { return false; }
    public function delete(User $user, mixed $model): bool { return false; }
    public function restore(User $user, mixed $model): bool { return false; }

    public function ativar(User $user, mixed $model): bool { return false; }
    public function requestApproval(User $user, mixed $model): bool { return false; }
    public function approve(User $user, mixed $model): bool { return false; }
    public function duplicate(User $user, mixed $model): bool { return false; }
    public function compare(User $user): bool { return false; }
    public function gerarDre(User $user, mixed $model): bool { return false; }
    public function recalcular(User $user, mixed $model): bool { return false; }
    public function reorder(User $user): bool { return false; }
    public function syncGantt(User $user, mixed $model): bool { return false; }
    public function recalcularProgresso(User $user, mixed $model): bool { return false; }
    public function export(User $user, mixed $model): bool { return false; }
    public function markReady(User $user, mixed $model): bool { return false; }
    public function cancel(User $user, mixed $model): bool { return false; }
}
