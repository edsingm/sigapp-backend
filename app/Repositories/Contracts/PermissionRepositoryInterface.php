<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;

interface PermissionRepositoryInterface
{
    /**
     * @return Collection<int, Permission>
     */
    public function list(?string $search = null): Collection;

    public function findById(int $id): ?Permission;

    public function create(string $name): Permission;

    public function update(Permission $permission, string $name): Permission;

    public function delete(Permission $permission): void;

    public function countRoleUsage(Permission $permission): int;

    public function countUserUsage(Permission $permission): int;

    public function isSystemPermission(string $name): bool;
}
