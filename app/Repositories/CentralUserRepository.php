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
        $flags = $this->extractAdminFlags($data);

        $user = new User;
        $user->fill($data);

        if ($flags !== []) {
            $user->forceFill($flags);
        }

        $user->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $flags = $this->extractAdminFlags($data);

        $user->fill($data);

        if ($flags !== []) {
            $user->forceFill($flags);
        }

        $user->save();

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, bool>
     */
    private function extractAdminFlags(array &$data): array
    {
        $flags = [];

        foreach (['is_admin', 'is_dpo'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $flags[$flag] = (bool) $data[$flag];
                unset($data[$flag]);
            }
        }

        return $flags;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
