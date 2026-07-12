<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\ViabilidadeScenario;
use Illuminate\Database\Eloquent\Collection;

class ViabilidadeScenarioRepository
{
    /**
     * @return Collection<int, ViabilidadeScenario>
     */
    public function listForViabilidade(int $viabilidadeId): Collection
    {
        return ViabilidadeScenario::query()
            ->where('viabilidade_id', $viabilidadeId)
            ->with('createdBy')
            ->latest('created_at')
            ->get();
    }

    public function findForViabilidadeOrFail(int $viabilidadeId, int $scenarioId): ViabilidadeScenario
    {
        return ViabilidadeScenario::query()
            ->where('viabilidade_id', $viabilidadeId)
            ->findOrFail($scenarioId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ViabilidadeScenario
    {
        return ViabilidadeScenario::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ViabilidadeScenario $scenario, array $data): ViabilidadeScenario
    {
        $scenario->update($data);

        return $scenario->refresh()->load('createdBy');
    }

    public function delete(ViabilidadeScenario $scenario): void
    {
        $scenario->delete();
    }
}
