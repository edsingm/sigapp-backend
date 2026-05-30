<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DashboardIndexRequest;
use App\Http\Resources\Admin\DashboardStatsResource;
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
        $recentTenantsPayload = [];
        $recentActivityPayload = [];

        foreach ($recentTenants as $tenant) {
            $plan = $tenant->plan()->first();
            $createdAt = $tenant->getAttribute('created_at');

            $recentTenantsPayload[] = [
                'id' => $tenant->getKey(),
                'name' => (string) $tenant->getAttribute('name'),
                'domain' => (string) $tenant->getAttribute('domain'),
                'plan' => $plan !== null ? [
                    'id' => $plan->getKey(),
                    'name' => (string) $plan->getAttribute('name'),
                ] : null,
                'status' => (string) $tenant->getAttribute('status'),
                'created_at' => $createdAt instanceof \DateTimeInterface ? $createdAt->format(DATE_ATOM) : null,
            ];
        }

        foreach ($recentActivity as $log) {
            $user = $log->user()->first();
            $createdAt = $log->getAttribute('created_at');

            $recentActivityPayload[] = [
                'id' => $log->getKey(),
                'action' => (string) $log->getAttribute('action'),
                'entity_type' => $log->getAttribute('entity_type'),
                'entity_id' => $log->getAttribute('entity_id'),
                'user' => $user !== null ? [
                    'id' => $user->getKey(),
                    'name' => (string) $user->getAttribute('name'),
                ] : null,
                'created_at' => $createdAt instanceof \DateTimeInterface ? $createdAt->format(DATE_ATOM) : null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats->toArray($request),
                'tenants_by_plan' => $tenantsByPlan,
                'trend' => $trend,
                'recent_tenants' => $recentTenantsPayload,
                'recent_activity' => $recentActivityPayload,
            ],
        ]);
    }
}
