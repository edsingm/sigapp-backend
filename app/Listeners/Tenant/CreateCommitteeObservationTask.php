<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Enums\WorkflowStatus;
use App\Events\Tenant\WorkflowTransitioned;
use App\Repositories\Contracts\LandWorkflowRepositoryInterface;

class CreateCommitteeObservationTask
{
    public function __construct(
        private readonly LandWorkflowRepositoryInterface $repository,
    ) {}

    public function handle(WorkflowTransitioned $event): void
    {
        if ($event->newStatus !== WorkflowStatus::NEGOCIACAO_MINUTA->value) {
            return;
        }

        if ($event->terreno->comiteAtual?->final_decision !== 'aprovado_com_ressalvas') {
            return;
        }

        $pendencias = $event->terreno->comiteAtual?->pendencias()->count() ?? 0;

        if ($pendencias > 0) {
            return;
        }

        $this->repository->createCommitteeObservationTask([
            'terreno_id' => $event->terreno->id,
            'related_type' => 'committee',
            'related_id' => $event->terreno->comiteAtual?->id,
            'title' => 'Resolver ressalvas do comitê',
            'description' => 'A aprovação com ressalvas exige tratativa e acompanhamento.',
            'assigned_to' => $event->terreno->responsavel_id,
            'status' => 'open',
            'priority' => 'high',
            'created_by' => $event->user?->id,
            'updated_by' => $event->user?->id,
        ]);
    }
}
