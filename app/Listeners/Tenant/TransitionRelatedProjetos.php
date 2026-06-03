<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Enums\WorkflowStatus;
use App\Events\Tenant\WorkflowTransitioned;
use App\Repositories\Contracts\LandWorkflowRepositoryInterface;

class TransitionRelatedProjetos
{
    public function __construct(
        private readonly LandWorkflowRepositoryInterface $repository,
    ) {}

    public function handle(WorkflowTransitioned $event): void
    {
        if ($event->newStatus === WorkflowStatus::LEGALIZANDO->value) {
            $this->repository->transitionProjetosToLegalizacao($event->terreno->id, $event->user?->id);

            return;
        }

        if ($event->newStatus === WorkflowStatus::LEGALIZADO_FINALIZADO->value) {
            $this->repository->transitionProjetosToFinalizado($event->terreno->id, $event->user?->id);

            return;
        }

        if (in_array($event->newStatus, WorkflowStatus::closure(), true)) {
            $this->repository->transitionProjetosToCancelado($event->terreno->id, $event->user?->id);
        }
    }
}
