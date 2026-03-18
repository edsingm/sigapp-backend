<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Obtém estatísticas do dashboard administrativo.
     *
     * GET /api/v1/admin/dashboard
     */
    public function index()
    {
        // Estatísticas principais de tenants
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', Tenant::STATUS_ACTIVE)->count();
        $pendingTenants = Tenant::where('status', Tenant::STATUS_PENDING)->count();
        $suspendedTenants = Tenant::where('status', Tenant::STATUS_SUSPENDED)->count();
        $cancelledTenants = Tenant::where('status', Tenant::STATUS_CANCELLED)->count();
        $todayTenants = Tenant::whereDate('created_at', today())->count();

        // Estatísticas de trial
        $trialTenants = Tenant::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->count();
        $trialExpiredTenants = Tenant::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->whereNotIn('status', [Tenant::STATUS_ACTIVE])
            ->count();

        // Cálculo de MRR (a partir de assinaturas ativas com planos)
        $mrr = Tenant::where('status', Tenant::STATUS_ACTIVE)
            ->whereNotNull('plan_id')
            ->with('plan')
            ->get()
            ->sum(function ($tenant) {
                return $tenant->plan?->price ?? 0;
            });

        // Distribuição de tenants por plano
        $tenantsByPlan = Plan::withCount(['tenants' => function ($query) {
            $query->where('status', Tenant::STATUS_ACTIVE);
        }])
            ->where('is_active', true)
            ->ordered()
            ->get()
            ->map(fn ($plan) => [
                'name' => $plan->name,
                'count' => $plan->tenants_count,
                'price' => $plan->price,
            ]);

        // Tenants criados por dia (últimos 30 dias) para gráfico
        $tenantsTrend = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $count = Tenant::whereDate('created_at', $date)->count();
            $tenantsTrend->push([
                'date' => $date->format('d/m'),
                'count' => $count,
            ]);
        }

        // Tenants recentes
        $recentTenants = Tenant::with('plan')
            ->latest()
            ->take(5)
            ->get(['id', 'name', 'slug', 'status', 'plan_id', 'trial_ends_at', 'created_at', 'admin_email']);

        // Atividade recente
        $recentActivity = AuditLog::with('user')
            ->latest()
            ->take(10)
            ->get();

        return ApiResponseService::success([
            'stats' => [
                'total_tenants' => $totalTenants,
                'active_tenants' => $activeTenants,
                'pending_tenants' => $pendingTenants,
                'suspended_tenants' => $suspendedTenants,
                'cancelled_tenants' => $cancelledTenants,
                'today_tenants' => $todayTenants,
                'trial_tenants' => $trialTenants,
                'trial_expired_tenants' => $trialExpiredTenants,
                'mrr' => $mrr,
            ],
            'tenants_by_plan' => $tenantsByPlan,
            'tenants_trend' => $tenantsTrend,
            'recent_tenants' => $recentTenants,
            'recent_activity' => $recentActivity,
        ], 'Dados do dashboard carregados com sucesso');
    }
}
