<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Department;
use App\Repositories\Tenant\DepartmentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DepartmentService
{
    public function __construct(
        private readonly DepartmentRepository $repository,
    ) {}

    /**
     * @param array{search?: string|null, active?: bool|null, sort?: string, order?: string, per_page?: int} $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Department>
     */
    public function listActiveForSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->allActive();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Department
    {
        return $this->repository->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Department $department, array $data): Department
    {
        return $this->repository->update($department, $data);
    }

    /**
     * Deletes a department, returning an error code if it is in use.
     */
    public function delete(Department $department): ?string
    {
        if ($this->repository->hasUsers($department)) {
            return 'DEPARTMENT_IN_USE';
        }

        $this->repository->delete($department);

        return null;
    }
}
