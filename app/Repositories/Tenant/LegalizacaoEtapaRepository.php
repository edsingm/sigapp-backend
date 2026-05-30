<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Legalizacao;
use App\Models\Tenant\LegalizacaoDependencia;
use App\Models\Tenant\LegalizacaoEtapa;
use Illuminate\Database\Eloquent\Collection;

class LegalizacaoEtapaRepository
{
    public function findById(int|string $id, array $relations = []): LegalizacaoEtapa
    {
        $query = LegalizacaoEtapa::query();

        if (! empty($relations)) {
            $query->with($relations);
        }

        return $query->findOrFail($id);
    }

    public function findByIdAndLegalizacao(int|string $id, int|string $legalizacaoId, array $relations = []): LegalizacaoEtapa
    {
        $query = LegalizacaoEtapa::query()->where('legalizacao_id', $legalizacaoId);

        if (! empty($relations)) {
            $query->with($relations);
        }

        return $query->findOrFail($id);
    }

    /**
     * @return Collection<int, LegalizacaoEtapa>
     */
    public function findByLegalizacao(int|string $legalizacaoId, array $relations = []): Collection
    {
        $query = LegalizacaoEtapa::query()->where('legalizacao_id', $legalizacaoId)
            ->orderBy('ordem');

        if (! empty($relations)) {
            $query->with($relations);
        }

        /** @var Collection<int, LegalizacaoEtapa> $etapas */
        $etapas = $query->get();

        return $etapas;
    }

    public function create(array $data): LegalizacaoEtapa
    {
        return LegalizacaoEtapa::query()->create($data);
    }

    public function update(LegalizacaoEtapa $etapa, array $data): LegalizacaoEtapa
    {
        $etapa->update($data);

        return $etapa->fresh();
    }

    public function findDependencyByEndpoints(
        int $legalizacaoId,
        int $etapaOrigemId,
        int $etapaDestinoId
    ): LegalizacaoDependencia {
        return LegalizacaoDependencia::query()->where('legalizacao_id', $legalizacaoId)
            ->where('etapa_origem_id', $etapaOrigemId)
            ->where('etapa_destino_id', $etapaDestinoId)
            ->firstOrFail();
    }

    public function delete(LegalizacaoEtapa $etapa): bool
    {
        return $etapa->delete();
    }

    /**
     * @param array<int, array{id: int|string, ordem: int}> $ordens
     */
    public function reorder(Legalizacao $legalizacao, array $ordens, int $updatedBy): void
    {
        foreach ($ordens as $ordemData) {
            if (! isset($ordemData['id'], $ordemData['ordem'])) {
                continue;
            }

            LegalizacaoEtapa::query()->where('legalizacao_id', $legalizacao->id)
                ->where('id', $ordemData['id'])
                ->update([
                    'ordem' => $ordemData['ordem'],
                    'updated_by' => $updatedBy,
                ]);
        }
    }

    /**
     * @param array<int, int|string> $etapaIds
     */
    public function deleteMany(array $etapaIds, int $legalizacaoId): int
    {
        return LegalizacaoEtapa::query()->where('legalizacao_id', $legalizacaoId)
            ->whereIn('id', $etapaIds)
            ->delete();
    }

    public function getNextOrdem(int $legalizacaoId): int
    {
        $ultimaOrdem = LegalizacaoEtapa::query()->where('legalizacao_id', $legalizacaoId)
            ->max('ordem');

        return $ultimaOrdem ? $ultimaOrdem + 1 : 1;
    }

    /**
     * Check if adding a dependency from origem to destino would create a cycle.
     */
    public function wouldCreateCycle(int $origemId, int $destinoId): bool
    {
        $visitados = [];
        $stack = [$destinoId];

        while (! empty($stack)) {
            $atual = array_pop($stack);

            if ($atual === $origemId) {
                return true;
            }

            if (in_array($atual, $visitados, true)) {
                continue;
            }

            $visitados[] = $atual;

            $proximos = LegalizacaoDependencia::query()->where('etapa_origem_id', $atual)
                ->pluck('etapa_destino_id')
                ->all();

            foreach ($proximos as $proximo) {
                if (! in_array($proximo, $visitados, true)) {
                    $stack[] = (int) $proximo;
                }
            }
        }

        return false;
    }

    public function dependencyExists(int $legalizacaoId, int $etapaOrigemId, int $etapaDestinoId): bool
    {
        return LegalizacaoDependencia::query()->where('legalizacao_id', $legalizacaoId)
            ->where('etapa_origem_id', $etapaOrigemId)
            ->where('etapa_destino_id', $etapaDestinoId)
            ->exists();
    }

    public function createDependency(array $data): LegalizacaoDependencia
    {
        return LegalizacaoDependencia::query()->create($data);
    }

    /**
     * @param array<int, int|string> $dependenciaIds
     */
    public function deleteDependencies(array $dependenciaIds, int $legalizacaoId): int
    {
        return LegalizacaoDependencia::query()->where('legalizacao_id', $legalizacaoId)
            ->whereIn('id', $dependenciaIds)
            ->delete();
    }
}
