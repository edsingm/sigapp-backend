<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    /**
     * @return Collection<int, Role>
     */
    public function forSelect(): Collection;

    /**
     * @return Collection<int, Role>
     */
    public function list(?string $search = null): Collection;

    public function findById(int $id): ?Role;

    public function create(string $name): Role;

    public function update(Role $role, string $name): Role;

    public function delete(Role $role): void;

    /**
     * @param  array<int>  $ids
     * @return Collection<int, Permission>
     */
    public function findPermissionsByIds(array $ids): Collection;

    public function countUsers(Role $role): int;
}
