<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\AuditLogRepository;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct(
        private readonly AuditLogRepository $repository
    ) {}

    /**
     * Lista todos os logs de auditoria.
     */
    public function index(Request $request): JsonResponse
    {
        if (! (bool) ($request->user()?->is_admin)) {
            return ApiResponseService::error('FORBIDDEN', 'FORBIDDEN', null, 403);
        }

        $action = $request->has('action') ? $request->get('action') : null;
        $userId = $request->has('user_id') ? (int) $request->get('user_id') : null;
        $perPage = min(100, max(1, (int) $request->get('per_page', 20)));

        $filters = [
            'action' => is_string($action) ? $action : null,
            'user_id' => $request->filled('user_id') ? $userId : null,
            'from' => $request->filled('from') ? (string) $request->get('from') : null,
            'to' => $request->filled('to') ? (string) $request->get('to') : null,
            'ip' => $request->filled('ip') ? (string) $request->get('ip') : null,
        ];

        $logs = $this->repository->paginateWithFilters(
            is_string($action) ? $action : null,
            $filters['user_id'],
            $perPage,
            $filters
        );

        $logs->through(function ($log): array {
            $user = $log->user;
            $metadata = is_array($log->metadata) ? $log->metadata : null;

            return [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'metadata' => $metadata,
                'user' => $user !== null ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'created_at' => $log->created_at?->toIso8601String(),
            ];
        });

        return ApiResponseService::paginated($logs, 'Logs de auditoria recuperados');
    }

    /**
     * Lista ações distintas para filtros da UI.
     */
    public function actions(Request $request): JsonResponse
    {
        if (! (bool) ($request->user()?->is_admin)) {
            return ApiResponseService::error('FORBIDDEN', 'FORBIDDEN', null, 403);
        }

        return ApiResponseService::success([
            'actions' => $this->repository->distinctActions(),
        ]);
    }
}
