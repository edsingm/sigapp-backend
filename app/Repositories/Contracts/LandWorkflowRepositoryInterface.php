<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tenant\EntityActivity;
use App\Models\Tenant\StatusHistory;
use App\Models\Tenant\Task;
use App\Models\Tenant\Terreno;

interface LandWorkflowRepositoryInterface
{
    /**
     * Carrega um terreno com todas as relações usadas durante uma transição de workflow.
     */
    public function loadTerrenoForTransition(int|string $terrenoId): Terreno;

    public function recordStatusHistory(array $data): StatusHistory;

    public function recordActivity(array $data): EntityActivity;

    public function createCommitteeObservationTask(array $data): Task;

    public function transitionProjetosToLegalizacao(int|string $terrenoId, ?int $userId): int;

    public function transitionProjetosToFinalizado(int|string $terrenoId, ?int $userId): int;

    public function transitionProjetosToCancelado(int|string $terrenoId, ?int $userId): int;
}
