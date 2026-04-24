<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\AuditLogRepository;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct(
        private readonly AuditLogRepository $repository
    ) {}

    /**
     * Lista todos os logs de auditoria.
     */
    public function index(Request $request)
    {
        $action = $request->has('action') ? $request->get('action') : null;
        $userId = $request->has('user_id') ? (int) $request->get('user_id') : null;

        $logs = $this->repository->paginateWithFilters($action, $userId);

        return ApiResponseService::success($logs, 'Logs de auditoria recuperados');
    }
}
