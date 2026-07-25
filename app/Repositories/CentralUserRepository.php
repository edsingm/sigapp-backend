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
        $isAdmin = array_key_exists('is_admin', $data) ? (bool) $data['is_admin'] : null;
        unset($data['is_admin']);

        $user = new User;
        $user->fill($data);

        if ($isAdmin !== null) {
            $user->forceFill(['is_admin' => $isAdmin]);
        }

        $user->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $isAdmin = array_key_exists('is_admin', $data) ? (bool) $data['is_admin'] : null;
        unset($data['is_admin']);

        $user->fill($data);

        if ($isAdmin !== null) {
            $user->forceFill(['is_admin' => $isAdmin]);
        }

        $user->save();

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
