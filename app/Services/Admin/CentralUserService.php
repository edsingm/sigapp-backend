<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Repositories\Contracts\CentralUserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CentralUserService
{
    public function __construct(
        private readonly CentralUserRepositoryInterface $userRepository
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepository->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return $this->userRepository->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        if (($data['password'] ?? null) === null) {
            unset($data['password']);
        }

        return $this->userRepository->update($user, $data);
    }

    public function delete(User $user, User $actor): bool
    {
        if ($user->is($actor)) {
            return false;
        }

        $this->userRepository->delete($user);

        return true;
    }
}
