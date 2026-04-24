<?php

namespace App\Http\Controllers\Api\V1\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreDepartmentRequest;
use App\Http\Requests\Tenant\UpdateDepartmentRequest;
use App\Http\Resources\Tenant\DepartmentResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\DepartmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $departments = $this->service->list([
            'search' => $request->filled('search') ? $request->string('search')->toString() : null,
            'active' => $request->has('active') ? $request->boolean('active') : null,
            'sort' => $request->string('sort', 'name')->toString(),
            'order' => $request->string('order', 'asc')->toString(),
            'per_page' => (int) $request->integer('per_page', 15),
        ]);

        $departments->through(fn ($department) => (new DepartmentResource($department))->toArray($request));

        return ApiResponseService::paginated($departments, 'Departments retrieved successfully.');
    }

    public function forSelect(): JsonResponse
    {
        $departments = $this->service->listActiveForSelect();

        return ApiResponseService::success(
            DepartmentResource::collection($departments),
            'Departments loaded successfully.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $department = $this->service->findById($id);

        if (! $department) {
            return ApiResponseService::notFound('Department not found.');
        }

        return ApiResponseService::success(new DepartmentResource($department), 'Department retrieved successfully.');
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = $this->service->create($request->validated());

        return ApiResponseService::created(new DepartmentResource($department), 'Department created successfully.');
    }

    public function update(UpdateDepartmentRequest $request, int $id): JsonResponse
    {
        $department = $this->service->findById($id);

        if (! $department) {
            return ApiResponseService::notFound('Department not found.');
        }

        $department = $this->service->update($department, $request->validated());

        return ApiResponseService::success(new DepartmentResource($department), 'Department updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $department = $this->service->findById($id);

        if (! $department) {
            return ApiResponseService::notFound('Department not found.');
        }

        $error = $this->service->delete($department);

        if ($error === 'DEPARTMENT_IN_USE') {
            return ApiResponseService::error(
                'DEPARTMENT_IN_USE',
                'Cannot delete a department that has users assigned to it.',
                null,
                422
            );
        }

        return ApiResponseService::noContent();
    }
}
