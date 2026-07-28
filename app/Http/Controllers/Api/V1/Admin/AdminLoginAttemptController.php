<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAdminLoginAttemptsRequest;
use App\Models\AdminLoginAttempt;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;

class AdminLoginAttemptController extends Controller
{
    public function index(ListAdminLoginAttemptsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $query = AdminLoginAttempt::query()->with('user:id,name,email')->latest('id');

        if (array_key_exists('successful', $data) && $data['successful'] !== null) {
            $query->where('successful', (bool) $data['successful']);
        }

        if (! empty($data['email'])) {
            $query->where('email', 'like', '%'.mb_strtolower(trim((string) $data['email'])).'%');
        }

        if (! empty($data['ip'])) {
            $query->where('ip_address', 'like', '%'.trim((string) $data['ip']).'%');
        }

        if (! empty($data['from'])) {
            $query->where('created_at', '>=', $data['from']);
        }

        if (! empty($data['to'])) {
            $query->where('created_at', '<=', $data['to']);
        }

        $perPage = (int) ($data['per_page'] ?? 50);

        $paginator = $query->paginate($perPage)->through(function (AdminLoginAttempt $row): array {
            $user = $row->user;

            return [
                'id' => $row->id,
                'email' => $row->email,
                'successful' => (bool) $row->successful,
                'failure_reason' => $row->failure_reason,
                'ip_address' => $row->ip_address,
                'user_agent' => $row->user_agent,
                'request_id' => $row->request_id,
                'user' => $user !== null ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'created_at' => $row->created_at?->toIso8601String(),
            ];
        });

        return ApiResponseService::paginated($paginator, 'DATA_RETRIEVED_SUCCESSFULLY');
    }
}
