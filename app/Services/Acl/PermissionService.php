<?php

declare(strict_types=1);

namespace App\Services\Acl;

use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;

class PermissionService
{
    public function __construct(
        private readonly PermissionRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, Permission>
     */
    public function list(?string $search = null): Collection
    {
        return $this->repository->list($search);
    }

    public function findById(int $id): ?Permission
    {
        return $this->repository->findById($id);
    }

    public function create(string $name): Permission
    {
        return $this->repository->create($name);
    }

    public function update(Permission $permission, string $name): Permission
    {
        return $this->repository->update($permission, $name);
    }

    public function canDelete(Permission $permission): bool
    {
        if ($this->repository->isSystemPermission($permission->name)) {
            return false;
        }

        if ($this->repository->countRoleUsage($permission) > 0) {
            return false;
        }

        return $this->repository->countUserUsage($permission) === 0;
    }

    public function delete(Permission $permission): void
    {
        $this->repository->delete($permission);
    }

    public function isSystemPermission(string $name): bool
    {
        return $this->repository->isSystemPermission($name);
    }
}
