<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAdminLoginAttemptsRequest;
use App\Http\Resources\AdminLoginAttemptResource;
use App\Repositories\AdminLoginAttemptRepository;
use Illuminate\Http\JsonResponse;

class AdminLoginAttemptController extends Controller
{
    public function __construct(
        private readonly AdminLoginAttemptRepository $repository,
    ) {}

    public function index(ListAdminLoginAttemptsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $perPage = (int) ($data['per_page'] ?? 50);

        return AdminLoginAttemptResource::collection(
            $this->repository->paginate($data, $perPage)
        )->response();
    }
}
