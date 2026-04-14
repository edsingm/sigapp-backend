<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PositionRepository
{
    /**
     * @param  array{search?: string|null, active?: bool|null, sort?: string, order?: string, per_page?: int}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Position::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }

        $sort = in_array($filters['sort'] ?? 'level', ['id', 'name', 'level', 'active', 'created_at'], true)
            ? $filters['sort']
            : 'level';
        $order = strtolower($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    /**
     * @return Collection<int, Position>
     */
    public function allActive(): Collection
    {
        return Position::where('active', true)->orderBy('level')->orderBy('name')->get();
    }

    public function findById(int $id): ?Position
    {
        return Position::find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Position
    {
        return Position::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Position $position, array $data): Position
    {
        $position->update($data);

        return $position->refresh();
    }

    public function delete(Position $position): void
    {
        $position->delete();
    }

    public function hasUsers(Position $position): bool
    {
        return $position->users()->exists();
    }

    /**
     * Returns positions hierarchically above the given level (for use in approval flows).
     *
     * @return Collection<int, Position>
     */
    public function findAboveLevel(int $level): Collection
    {
        return Position::where('level', '<', $level)
            ->where('active', true)
            ->orderBy('level')
            ->get();
    }
}
