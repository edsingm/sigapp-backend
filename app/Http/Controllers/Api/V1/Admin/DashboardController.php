<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardIndexRequest;
use App\Http\Resources\Admin\DashboardStatsResource;
use App\Services\ApiResponseService;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function index(DashboardIndexRequest $request): JsonResponse
    {
        $stats = new DashboardStatsResource($this->dashboardService->stats());
        $tenantsByPlan = $this->dashboardService->tenantsByPlan();
        $trend = $this->dashboardService->tenantsTrend();
        $recentTenants = $this->dashboardService->recentTenants();
        $recentActivity = $this->dashboardService->recentActivity();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats->toArray($request),
                'tenants_by_plan' => $tenantsByPlan,
                'trend' => $trend,
                'recent_tenants' => $recentTenants->map(fn ($tenant) => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'domain' => $tenant->domain,
                    'plan' => $tenant->plan ? [
                        'id' => $tenant->plan->id,
                        'name' => $tenant->plan->name,
                    ] : null,
                    'status' => $tenant->status,
                    'created_at' => $tenant->created_at?->toIso8601String(),
                ]),
                'recent_activity' => $recentActivity->map(fn ($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'entity_type' => $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                    ] : null,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }
}
