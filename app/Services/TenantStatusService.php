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
    private const CACHE_KEY = 'aggregated_stats';

    /**
     * TTL em segundos (1 hora). O cache é invalidado pelo RefreshTenantStatsJob.
     */
    private const CACHE_TTL = 3600;

    /**
     * Retorna estatísticas agregadas de todos os tenants.
     * Usa cache com TTL de 1 hora. Se o cache não existir, retorna dados
     * básicos (contagem de tenants) e dispara atualização assíncrona.
     *
     * @return array{total_tenants: int, total_terrenos: int, total_projetos: int, total_usuarios: int, stale?: bool}
     */
    public function getAggregatedStats(): array
    {
        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && array_key_exists('total_tenants', $cached)) {
            /** @var array{total_tenants: int, total_terrenos: int, total_projetos: int, total_usuarios: int} */
            return $cached;
        }

        // Retorna dados básicos imediatamente enquanto o Job calcula o resto
        $basicStats = [
            'total_tenants' => Tenant::query()->count(),
            'total_terrenos' => 0,
            'total_projetos' => 0,
            'total_usuarios' => 0,
            'stale' => true,
        ];

        // Dispara Job assíncrono para calcular estatísticas completas
        \App\Jobs\RefreshTenantStatsJob::dispatch();

        return $basicStats;
    }

    /**
     * Calcula e armazena as estatísticas agregadas.
     * Chamado pelo RefreshTenantStatsJob.
     *
     * @return array{total_tenants: int, total_terrenos: int, total_projetos: int, total_usuarios: int}
     */
    public function refreshStats(): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->get(['id', 'slug']);
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

        $stats = [
            'total_tenants' => $totalTenants,
            'total_terrenos' => $totalTerrenos,
            'total_projetos' => $totalProjetos,
            'total_usuarios' => $totalUsuarios,
        ];

        Cache::put(self::CACHE_KEY, $stats, self::CACHE_TTL);

        return $stats;
    }
}
