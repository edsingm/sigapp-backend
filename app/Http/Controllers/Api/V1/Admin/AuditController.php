<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    /**
     * List all audit logs.
     */
    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        if ($request->has('action')) {
            $action = $request->get('action');
            // Support prefix filtering (e.g. 'tenant.signup' matches 'tenant.signup_started', etc.)
            if (str_contains($action, '*') || !str_contains($action, '_')) {
                $query->where('action', 'LIKE', rtrim($action, '*') . '%');
            } else {
                $query->where('action', $action);
            }
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        $logs = $query->latest()->paginate(20);

        return ApiResponseService::success($logs, 'Logs de auditoria recuperados');
    }
}
