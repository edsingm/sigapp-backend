<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Tenant\Projeto;
use App\Models\Tenant\ProjetoDependency;
use App\Models\Tenant\ProjetoMilestone;
use App\Models\Tenant\ProjetoRisk;
use Illuminate\Database\Eloquent\Collection;

class ProjetoPlanningRepository
{
    public function findProjeto(int $id): Projeto
    {
        return Projeto::query()->findOrFail($id);
    }

    /** @return Collection<int, ProjetoMilestone> */
    public function milestones(Projeto $projeto): Collection
    {
        $ids = $projeto->milestones()->pluck('id')->all();
        $milestones = ProjetoMilestone::query()->findMany($ids);
        $milestones->load('responsavel');

        return new Collection($milestones->sortBy('position')->values()->all());
    }

    public function createMilestone(array $data): ProjetoMilestone
    {
        return ProjetoMilestone::create($data)->load('responsavel');
    }

    public function findMilestone(Projeto $projeto, int $id): ProjetoMilestone
    {
        return $projeto->milestones()->findOrFail($id);
    }

    public function updateMilestone(ProjetoMilestone $milestone, array $data): ProjetoMilestone
    {
        $milestone->update($data);

        return $milestone->fresh('responsavel') ?? throw new \RuntimeException('Milestone não encontrado após atualização.');
    }

    public function deleteMilestone(ProjetoMilestone $milestone): void
    {
        $milestone->delete();
    }

    /** @return Collection<int, ProjetoMilestone> */
    public function milestonesByIds(Projeto $projeto, array $ids): Collection
    {
        return $projeto->milestones()->whereKey($ids)->get();
    }

    /** @return Collection<int, ProjetoDependency> */
    public function dependencies(Projeto $projeto): Collection
    {
        return $projeto->dependencies()
            ->with(['predecessor', 'successor'])
            ->get();
    }

    public function findDependency(Projeto $projeto, int $id): ProjetoDependency
    {
        return $projeto->dependencies()->findOrFail($id);
    }

    public function dependencyExists(Projeto $projeto, int $predecessorId, int $successorId): bool
    {
        return $projeto->dependencies()
            ->where('predecessor_milestone_id', $predecessorId)
            ->where('successor_milestone_id', $successorId)
            ->exists();
    }

    public function createDependency(array $data): ProjetoDependency
    {
        return ProjetoDependency::create($data)->load(['predecessor', 'successor']);
    }

    public function deleteDependency(ProjetoDependency $dependency): void
    {
        $dependency->delete();
    }

    /** @return Collection<int, ProjetoDependency> */
    public function dependencyGraph(Projeto $projeto): Collection
    {
        return $projeto->dependencies()->get();
    }

    /** @return Collection<int, ProjetoRisk> */
    public function risks(Projeto $projeto): Collection
    {
        return $projeto->risks()->with('responsavel')->latest()->get();
    }

    public function findRisk(Projeto $projeto, int $id): ProjetoRisk
    {
        return $projeto->risks()->findOrFail($id);
    }

    public function createRisk(array $data): ProjetoRisk
    {
        return ProjetoRisk::create($data)->load('responsavel');
    }

    public function updateRisk(ProjetoRisk $risk, array $data): ProjetoRisk
    {
        $risk->update($data);

        return $risk->fresh('responsavel') ?? throw new \RuntimeException('Risco não encontrado após atualização.');
    }

    public function deleteRisk(ProjetoRisk $risk): void
    {
        $risk->delete();
    }
}
