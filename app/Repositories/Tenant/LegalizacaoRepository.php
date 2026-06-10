<?php

namespace App\Repositories\Tenant;

use App\Enums\WorkflowStatus;
use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoDependencia;
use App\Models\Tenant\LegalizacaoPendencia;
use App\Models\Tenant\Terreno;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class LegalizacaoRepository
{
    /**
     * @var list<string>
     */
    private const LIST_RELATIONS = [
        'terreno',
        'responsavel',
        'etapas',
        'createdBy',
        'updatedBy',
    ];

    /**
     * @var list<string>
     */
    private const DETAIL_RELATIONS = [
        'terreno',
        'responsavel',
        'etapas.responsavel',
        'etapas.dependenciasDestino',
        'etapas.dependenciasOrigem',
        'pendencias',
        'createdBy',
        'updatedBy',
    ];

    public function findOrFail(int|string $id): Legalizacao
    {
        return Legalizacao::query()->findOrFail($id);
    }

    /**
     * @param  array{search?: string|null, terreno_id?: int|string|null, status?: string|null, per_page?: int|null}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Legalizacao::query()->with(self::LIST_RELATIONS);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('nome', 'like', "%{$search}%")
                    ->orWhereHas('terreno', function ($terrainBuilder) use ($search): void {
                        $terrainBuilder->where('nome', 'like', "%{$search}%")
                            ->orWhere('endereco', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['terreno_id'])) {
            $query->where('terreno_id', $filters['terreno_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 10));
    }

    public function loadDetail(Legalizacao $legalizacao): Legalizacao
    {
        return $legalizacao->fresh(self::DETAIL_RELATIONS);
    }

    public function findTerrenoOrFail(int|string $terrenoId): Terreno
    {
        return Terreno::query()->findOrFail($terrenoId);
    }

    public function existsByTerreno(int $terrenoId): bool
    {
        return Legalizacao::query()->where('terreno_id', $terrenoId)->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Legalizacao
    {
        return Legalizacao::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Legalizacao $legalizacao, array $data): Legalizacao
    {
        $legalizacao->update($data);

        return $legalizacao->refresh();
    }

    public function delete(Legalizacao $legalizacao): bool
    {
        return (bool) $legalizacao->delete();
    }

    public function recalculateProgress(Legalizacao $legalizacao): Legalizacao
    {
        $legalizacao->recalcularProgresso();

        return $legalizacao->refresh();
    }

    public function hasCriticalOpenPendencia(int $legalizacaoId): bool
    {
        return LegalizacaoPendencia::query()
            ->where('legalizacao_id', $legalizacaoId)
            ->where('status', 'open')
            ->where('is_critical', true)
            ->exists();
    }

    /**
     * @return Collection<int, LegalizacaoDependencia>
     */
    public function listDependenciasByLegalizacao(int $legalizacaoId)
    {
        return LegalizacaoDependencia::query()
            ->with(['etapaOrigem', 'etapaDestino'])
            ->where('legalizacao_id', $legalizacaoId)
            ->get();
    }

    /**
     * @param  array{search?: string|null, per_page?: int|null}  $filters
     */
    public function paginateEligibleTerrenos(array $filters = []): LengthAwarePaginator
    {
        $query = Terreno::query()
            ->with(['cidade', 'responsavel'])
            ->where('workflow_status_code', WorkflowStatus::CONTRATO_ASSINADO->value)
            ->whereDoesntHave('legalizacao');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('nome', 'like', "%{$search}%")
                    ->orWhere('endereco', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 10));
    }
}
