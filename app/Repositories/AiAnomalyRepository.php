<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\Terreno;
use App\Repositories\Contracts\AiAnomalyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AiAnomalyRepository implements AiAnomalyRepositoryInterface
{
    private const TERMINAL_STATUSES = ['descartado', 'arquivado'];

    private const FINAL_STATUSES = ['descartado', 'arquivado', 'legalizado_finalizado'];

    public function getActiveForWorkflowCheck(int $limit): Collection
    {
        /** @var Collection<int, Terreno> $terrenos */
        $terrenos = Terreno::query()
            ->whereNotIn('workflow_status_code', self::TERMINAL_STATUSES)
            ->with(['viabilidadeAtual', 'comiteAtual', 'contratoAtual', 'legalizacao'])
            ->limit($limit)
            ->get();

        return $terrenos;
    }

    public function getWithViabilidadesForFinancialCheck(int $limit): Collection
    {
        /** @var Collection<int, Terreno> $terrenos */
        $terrenos = Terreno::query()
            ->whereNotNull('valor')
            ->where('valor', '>', 0)
            ->with(['viabilidades' => function ($q) {
                $q->withTrashed()->whereNotNull('approval_status');
            }])
            ->limit($limit)
            ->get();

        return $terrenos;
    }

    public function getAllActiveForDuplicateCheck(int $initialLimit): Collection
    {
        /** @var Collection<int, Terreno> $terrenos */
        $terrenos = Terreno::query()
            ->whereNotIn('workflow_status_code', self::TERMINAL_STATUSES)
            ->orderBy('id')
            ->limit($initialLimit)
            ->get();

        return $terrenos;
    }

    public function getActiveAfterId(int $terrenoId, int $limit): Collection
    {
        /** @var Collection<int, Terreno> $terrenos */
        $terrenos = Terreno::query()
            ->whereNotIn('workflow_status_code', self::TERMINAL_STATUSES)
            ->where('id', '>', $terrenoId)
            ->limit($limit)
            ->get();

        return $terrenos;
    }

    public function getZeroValue(int $limit): Collection
    {
        /** @var Collection<int, Terreno> $terrenos */
        $terrenos = Terreno::query()
            ->whereNotIn('workflow_status_code', self::FINAL_STATUSES)
            ->where('valor', 0)
            ->limit($limit)
            ->get(['id', 'nome', 'area_terreno', 'updated_at']);

        return $terrenos;
    }

    public function getMissingArea(int $limit): Collection
    {
        /** @var Collection<int, Terreno> $terrenos */
        $terrenos = Terreno::query()
            ->whereNotIn('workflow_status_code', self::FINAL_STATUSES)
            ->where(function ($q) {
                $q->where('area_terreno', 0)
                    ->orWhereNull('area_terreno');
            })
            ->limit($limit)
            ->get(['id', 'nome', 'updated_at']);

        return $terrenos;
    }
}
