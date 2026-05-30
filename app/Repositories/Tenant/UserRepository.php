<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    public function find(int|string $id): ?User
    {
        return User::query()->find($id);
    }

    /**
     * @param  array<int, string>  $relations
     */
    public function findWithRelations(int|string $id, array $relations): ?User
    {
        return User::query()
            ->with($relations)
            ->find($id);
    }

    /**
     * @return Collection<int, User>
     */
    public function listForSelect(): Collection
    {
        /** @var Collection<int, User> $users */
        $users = User::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->limit(200)
            ->get();

        return $users;
    }

    /**
     * @return Builder<User>
     */
    public function queryWithRelations(): Builder
    {
        return User::query()->with(['roles', 'department', 'position']);
    }

    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function adminEligibleCount(array $adminRoleNames): int
    {
        return User::query()
            ->whereHas('roles', function ($q) use ($adminRoleNames) {
                $q->whereIn('name', $adminRoleNames)
                    ->where('guard_name', 'web');
            })
            ->count();
    }
}
