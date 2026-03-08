<?php

namespace App\Services;

use App\Models\Central\Tenant;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class TenantStatusService
{
    public function getAggregatedStats()
    {
        return Cache::remember('aggregated_stats', 600, function () {
            $tenants = Tenant::query()->get();
            $totalTenants = $tenants->count();
            $totalTerrenos = 0;
            $totalProjetos = 0;
            $totalUsuarios = 0;

            foreach ($tenants as $tenant) {
                try {
                    $counts = $tenant->run(function () {
                        return [
                            'terrenos' => Terreno::query()->count(),
                            'projetos' => Projeto::query()->count(),
                            'usuarios' => User::query()->count(),
                        ];
                    });

                    $totalTerrenos += (int) ($counts['terrenos'] ?? 0);
                    $totalProjetos += (int) ($counts['projetos'] ?? 0);
                    $totalUsuarios += (int) ($counts['usuarios'] ?? 0);
                } catch (Throwable $e) {
                    Log::error('Erro ao agregar estatísticas do tenant', [
                        'tenant_id' => (string) $tenant->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'total_tenants' => $totalTenants,
                'total_terrenos' => $totalTerrenos,
                'total_projetos' => $totalProjetos,
                'total_usuarios' => $totalUsuarios,
            ];
        });
    }
}
