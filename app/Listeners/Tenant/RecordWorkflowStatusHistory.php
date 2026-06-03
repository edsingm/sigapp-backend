<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\WorkflowTransitioned;
use App\Repositories\Contracts\LandWorkflowRepositoryInterface;

class RecordWorkflowStatusHistory
{
    public function __construct(
        private readonly LandWorkflowRepositoryInterface $repository,
    ) {}

    public function handle(WorkflowTransitioned $event): void
    {
        $this->repository->recordStatusHistory([
            'terreno_id' => $event->terreno->id,
            'old_stage' => $event->previousStage,
            'old_status_code' => $event->previousStatus,
            'new_stage' => $event->newStage,
            'new_status_code' => $event->newStatus,
            'changed_by' => $event->user?->id,
            'reason_code' => $event->reasonCode,
            'reason' => $event->reasonNotes,
            'metadata_json' => [
                'label' => $event->newLabel,
            ],
            'created_at' => now(),
        ]);
    }
}
