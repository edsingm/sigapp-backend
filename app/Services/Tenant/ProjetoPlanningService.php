<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Projeto;
use App\Models\Tenant\ProjetoDependency;
use App\Models\Tenant\ProjetoMilestone;
use App\Models\Tenant\ProjetoRisk;
use App\Repositories\ProjetoPlanningRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProjetoPlanningService
{
    public function __construct(
        private readonly ProjetoPlanningRepository $repository,
    ) {}

    /** @return Collection<int, ProjetoMilestone> */
    public function milestones(Projeto $projeto): Collection
    {
        return $this->repository->milestones($projeto);
    }

    public function findProjeto(int $id): Projeto
    {
        return $this->repository->findProjeto($id);
    }

    public function findMilestone(Projeto $projeto, int $id): ProjetoMilestone
    {
        return $this->repository->findMilestone($projeto, $id);
    }

    public function findRisk(Projeto $projeto, int $id): ProjetoRisk
    {
        return $this->repository->findRisk($projeto, $id);
    }

    public function createMilestone(Projeto $projeto, array $data): ProjetoMilestone
    {
        return $this->repository->createMilestone([
            ...$data,
            'projeto_id' => $projeto->getKey(),
        ]);
    }

    public function updateMilestone(Projeto $projeto, ProjetoMilestone $milestone, array $data): ProjetoMilestone
    {
        $this->assertMilestoneBelongsToProjeto($projeto, $milestone);

        return $this->repository->updateMilestone($milestone, $data);
    }

    public function deleteMilestone(Projeto $projeto, ProjetoMilestone $milestone): void
    {
        $this->assertMilestoneBelongsToProjeto($projeto, $milestone);
        $this->repository->deleteMilestone($milestone);
    }

    /**
     * @param  array<int, int>  $milestoneIds
     * @return Collection<int, ProjetoMilestone>
     */
    public function reorderMilestones(Projeto $projeto, array $milestoneIds): Collection
    {
        return DB::transaction(function () use ($projeto, $milestoneIds): Collection {
            $milestones = $this->repository->milestonesByIds($projeto, $milestoneIds);
            if ($milestones->count() !== count($milestoneIds) || count(array_unique($milestoneIds)) !== count($milestoneIds)) {
                throw new RuntimeException('A ordenação contém milestones que não pertencem ao projeto.');
            }

            foreach ($milestoneIds as $position => $milestoneId) {
                $milestones->firstWhere('id', $milestoneId)?->update(['position' => $position]);
            }

            return $this->repository->milestones($projeto);
        });
    }

    /** @return Collection<int, ProjetoDependency> */
    public function dependencies(Projeto $projeto): Collection
    {
        return $this->repository->dependencies($projeto);
    }

    public function createDependency(Projeto $projeto, array $data): ProjetoDependency
    {
        $predecessorId = (int) $data['predecessor_milestone_id'];
        $successorId = (int) $data['successor_milestone_id'];

        if ($predecessorId === $successorId) {
            throw new RuntimeException('Um milestone não pode depender de si mesmo.');
        }

        $milestones = $this->repository->milestonesByIds($projeto, [$predecessorId, $successorId]);
        if ($milestones->count() !== 2) {
            throw new RuntimeException('Os milestones da dependência devem pertencer ao projeto.');
        }

        if ($this->repository->dependencyExists($projeto, $predecessorId, $successorId)) {
            throw new RuntimeException('Esta dependência já existe.');
        }

        if ($this->createsCycle($projeto, $predecessorId, $successorId)) {
            throw new RuntimeException('A dependência criaria um ciclo no planejamento.');
        }

        return $this->repository->createDependency([
            ...$data,
            'projeto_id' => $projeto->getKey(),
        ]);
    }

    public function deleteDependency(Projeto $projeto, int $dependencyId): void
    {
        $this->repository->deleteDependency($this->repository->findDependency($projeto, $dependencyId));
    }

    /** @return Collection<int, ProjetoRisk> */
    public function risks(Projeto $projeto): Collection
    {
        return $this->repository->risks($projeto);
    }

    public function createRisk(Projeto $projeto, array $data): ProjetoRisk
    {
        return $this->repository->createRisk([
            ...$data,
            'projeto_id' => $projeto->getKey(),
        ]);
    }

    public function updateRisk(Projeto $projeto, ProjetoRisk $risk, array $data): ProjetoRisk
    {
        $this->assertRiskBelongsToProjeto($projeto, $risk);

        return $this->repository->updateRisk($risk, $data);
    }

    public function deleteRisk(Projeto $projeto, int $riskId): void
    {
        $this->repository->deleteRisk($this->repository->findRisk($projeto, $riskId));
    }

    private function createsCycle(Projeto $projeto, int $predecessorId, int $successorId): bool
    {
        $graph = [];
        foreach ($this->repository->dependencyGraph($projeto) as $dependency) {
            $graph[$dependency->predecessor_milestone_id][] = $dependency->successor_milestone_id;
        }
        $graph[$predecessorId][] = $successorId;

        $visited = [];
        $pending = [$successorId];
        while ($pending !== []) {
            $current = array_pop($pending);
            if ($current === $predecessorId) {
                return true;
            }
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            foreach ($graph[$current] ?? [] as $next) {
                $pending[] = $next;
            }
        }

        return false;
    }

    private function assertMilestoneBelongsToProjeto(Projeto $projeto, ProjetoMilestone $milestone): void
    {
        if ($milestone->projeto_id !== $projeto->id) {
            throw new RuntimeException('Milestone não pertence ao projeto informado.');
        }
    }

    private function assertRiskBelongsToProjeto(Projeto $projeto, ProjetoRisk $risk): void
    {
        if ($risk->projeto_id !== $projeto->id) {
            throw new RuntimeException('Risco não pertence ao projeto informado.');
        }
    }
}
