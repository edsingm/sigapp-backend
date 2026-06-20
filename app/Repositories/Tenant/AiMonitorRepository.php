<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\Terreno;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class AiMonitorRepository
{
    /**
     * Retorna terrenos sem atualização desde o threshold informado.
     *
     * @return Collection<int, Terreno>
     */
    public function stalledTerrains(int $limit, CarbonInterface $threshold): Collection
    {
        return Terreno::query()
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])
            ->where(function ($q) use ($threshold): void {
                $q->whereNull('workflow_status_changed_at')
                    ->orWhere('updated_at', '<', $threshold);
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Retorna terrenos ativos com relações de workflow carregadas para checar inconsistências.
     *
     * @return Collection<int, Terreno>
     */
    public function terrenosWithInconsistencies(int $limit): Collection
    {
        return Terreno::query()
            ->with(['viabilidadeAtual', 'comiteAtual'])
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])
            ->limit($limit)
            ->get();
    }

    /**
     * Retorna terrenos ativos com tasks e etapas de legalização carregadas para checar atrasos.
     *
     * @return Collection<int, Terreno>
     */
    public function terrenosWithOverdueItems(int $limit): Collection
    {
        return Terreno::query()
            ->with(['tasks', 'legalizacao.etapas'])
            ->whereNotIn('workflow_status_code', ['descartado', 'arquivado', 'legalizado_finalizado'])
            ->limit($limit)
            ->get();
    }
}
