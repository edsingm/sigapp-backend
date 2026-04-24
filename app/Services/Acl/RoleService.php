<?php

declare(strict_types=1);

namespace App\Services\Acl;

use App\Enums\Common\RolesEnum;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, Role>
     */
    public function forSelect(): Collection
    {
        return $this->repository->forSelect();
    }

    /**
     * @return Collection<int, Role>
     */
    public function list(?string $search = null): Collection
    {
        return $this->repository->list($search);
    }

    public function findById(int $id): ?Role
    {
        return $this->repository->findById($id);
    }

    public function create(string $name, array $permissionIds = []): Role
    {
        $role = $this->repository->create($name);

        if ($permissionIds !== []) {
            $permissions = $this->repository->findPermissionsByIds($permissionIds);
            $role->permissions()->sync($permissions);
        }

        return $role->load('permissions');
    }

    public function update(Role $role, string $name, ?array $permissionIds = null): Role
    {
        $role = $this->repository->update($role, $name);

        if ($permissionIds !== null) {
            $permissions = $this->repository->findPermissionsByIds($permissionIds);
            $role->permissions()->sync($permissions);
        }

        return $role->load('permissions');
    }

    public function canDelete(Role $role): bool
    {
        if ($this->isProtected($role)) {
            return false;
        }

        return $this->repository->countUsers($role) === 0;
    }

    public function delete(Role $role): void
    {
        $this->repository->delete($role);
    }

    public function isProtected(Role $role): bool
    {
        return in_array($role->name, array_column(RolesEnum::cases(), 'value'), true);
    }

    public function countUsers(Role $role): int
    {
        return $this->repository->countUsers($role);
    }
}
