<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DepartmentRepository
{
    /**
     * @param  array{search?: string|null, active?: bool|null, sort?: string, order?: string, per_page?: int}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Department::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }

        $sort = in_array($filters['sort'] ?? 'name', ['id', 'name', 'active', 'created_at'], true)
            ? $filters['sort']
            : 'name';
        $order = strtolower($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->orderBy($sort, $order)->paginate($perPage);
    }

    /**
     * @return Collection<int, Department>
     */
    public function allActive(): Collection
    {
        return Department::where('active', true)->orderBy('name')->get();
    }

    public function findById(int $id): ?Department
    {
        return Department::find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Department
    {
        return Department::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department->refresh();
    }

    public function delete(Department $department): void
    {
        $department->delete();
    }

    public function hasUsers(Department $department): bool
    {
        return $department->users()->exists();
    }
}
