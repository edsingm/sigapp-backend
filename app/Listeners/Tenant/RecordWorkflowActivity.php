<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\WorkflowTransitioned;
use App\Models\Tenant\Terreno;
use App\Repositories\Contracts\LandWorkflowRepositoryInterface;

class RecordWorkflowActivity
{
    public function __construct(
        private readonly LandWorkflowRepositoryInterface $repository,
    ) {}

    public function handle(WorkflowTransitioned $event): void
    {
        $this->repository->recordActivity([
            'terreno_id' => $event->terreno->id,
            'entity_type' => Terreno::class,
            'entity_id' => $event->terreno->id,
            'action' => 'workflow.transition',
            'user_id' => $event->user?->id,
            'summary' => "Workflow alterado para {$event->newLabel}",
            'payload_json' => [
                'old_stage' => $event->previousStage,
                'old_status_code' => $event->previousStatus,
                'new_stage' => $event->newStage,
                'new_status_code' => $event->newStatus,
                'reason_code' => $event->reasonCode,
                'reason_notes' => $event->reasonNotes,
            ],
            'happened_at' => now(),
        ]);
    }
}
