<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\CentralUserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CentralUserRepository implements CentralUserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function findById(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
