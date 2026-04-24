<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Position;
use App\Repositories\Tenant\PositionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PositionService
{
    public function __construct(
        private readonly PositionRepository $repository,
    ) {}

    /**
     * @param  array{search?: string|null, active?: bool|null, sort?: string, order?: string, per_page?: int}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    /**
     * @return Collection<int, Position>
     */
    public function listActiveForSelect(): Collection
    {
        return $this->repository->allActive();
    }

    public function findById(int $id): ?Position
    {
        return $this->repository->findById($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Position
    {
        return $this->repository->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Position $position, array $data): Position
    {
        return $this->repository->update($position, $data);
    }

    /**
     * Deletes a position, returning an error code if it is in use.
     */
    public function delete(Position $position): ?string
    {
        if ($this->repository->hasUsers($position)) {
            return 'POSITION_IN_USE';
        }

        $this->repository->delete($position);

        return null;
    }

    /**
     * Returns positions hierarchically above the given position's level.
     * Used in approval flows to determine approvers.
     *
     * @return Collection<int, Position>
     */
    public function findApproversAbove(Position $position): Collection
    {
        return $this->repository->findAboveLevel($position->level);
    }
}
