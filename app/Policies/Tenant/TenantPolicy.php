<?php

namespace App\Policies\Tenant;

use App\Enums\Common\ModulesEnum;
use App\Models\Tenant\User;

/**
 * Single policy for all tenant models.
 *
 * All logic lives in before(), which Laravel calls with the model class (for
 * viewAny/create) or model instance (for view/update/delete/etc.) as the third
 * argument — before any named policy method is invoked.
 *
 * Ability → minimum required level:
 *   viewAny / view            → viewer
 *   create  / update          → editor
 *   everything else           → manager  (delete, restore, export, ativar, approve…)
 *
 * Admin and super_admin bypass all checks (before() returns true immediately).
 */
class TenantPolicy
{
    /**
     * Called before any policy method. Handles all authorization.
     *
     * Returning non-null short-circuits all named methods below.
     * Returning null falls through to the named method (which denies by default).
     *
     * Model → module mapping lives in ModulesEnum::models() — add new models there.
     */
    public function before(User $user, string $ability, mixed $modelOrClass = null): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $class  = is_object($modelOrClass) ? get_class($modelOrClass) : (string) $modelOrClass;
        $module = ModulesEnum::modelMap()[$class] ?? null;

        if (!$module) {
            return null;
        }

        $level = match (true) {
            in_array($ability, ['viewAny', 'view'], true) => 'viewer',
            in_array($ability, ['create', 'update'], true) => 'editor',
            default => 'manager',
        };

        return $user->can("{$module}.{$level}");
    }

    // ── Default-deny fallbacks (only reached when model is not in MODEL_MAP) ──

    public function viewAny(User $user): bool { return false; }
    public function view(User $user, mixed $model): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, mixed $model): bool { return false; }
    public function delete(User $user, mixed $model): bool { return false; }
    public function restore(User $user, mixed $model): bool { return false; }
}

