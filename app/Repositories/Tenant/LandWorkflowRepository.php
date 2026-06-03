<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Enums\ProjetoStatus;
use App\Models\Tenant\EntityActivity;
use App\Models\Tenant\Projeto;
use App\Models\Tenant\StatusHistory;
use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;
use App\Repositories\Contracts\LandWorkflowRepositoryInterface;

class LandWorkflowRepository implements LandWorkflowRepositoryInterface
{
    public function loadTerrenoForTransition(int|string $terrenoId): Terreno
    {
        return Terreno::query()
            ->with([
                'proprietarios',
                'terrenoProdutos',
                'viabilidadeAtual',
                'viabilidades',
                'comiteAtual.pareceresDepartamento',
                'comiteAtual.pendencias',
                'negociacaoAtual',
                'contratoAtual.partes',
                'legalizacao.etapas',
                'legalizacao.pendencias',
            ])
            ->findOrFail($terrenoId);
    }

    public function recordStatusHistory(array $data): StatusHistory
    {
        return StatusHistory::create($data);
    }

    public function recordActivity(array $data): EntityActivity
    {
        return EntityActivity::create($data);
    }

    public function createCommitteeObservationTask(array $data): Task
    {
        return Task::create($data);
    }

    public function transitionProjetosToLegalizacao(int|string $terrenoId, ?int $userId): int
    {
        return Projeto::where('terreno_id', $terrenoId)
            ->where('status', ProjetoStatus::EM_VIABILIDADE)
            ->update([
                'status' => ProjetoStatus::EM_LEGALIZACAO,
                'updated_by' => $userId,
            ]);
    }

    public function transitionProjetosToFinalizado(int|string $terrenoId, ?int $userId): int
    {
        return Projeto::where('terreno_id', $terrenoId)
            ->whereNotIn('status', [ProjetoStatus::CANCELADO, ProjetoStatus::FINALIZADO])
            ->update([
                'status' => ProjetoStatus::FINALIZADO,
                'updated_by' => $userId,
            ]);
    }

    public function transitionProjetosToCancelado(int|string $terrenoId, ?int $userId): int
    {
        return Projeto::where('terreno_id', $terrenoId)
            ->whereNotIn('status', [ProjetoStatus::CANCELADO, ProjetoStatus::FINALIZADO])
            ->update([
                'status' => ProjetoStatus::CANCELADO,
                'updated_by' => $userId,
            ]);
    }
}
