<?php

declare(strict_types=1);

namespace App\Listeners\Tenant;

use App\Events\Tenant\ContratoSigned;
use App\Models\Tenant\Contrato;
use App\Repositories\Contracts\LandWorkflowRepositoryInterface;

class RecordContractSignedActivity
{
    public function __construct(
        private readonly LandWorkflowRepositoryInterface $repository,
    ) {}

    public function handle(ContratoSigned $event): void
    {
        $this->repository->recordActivity([
            'terreno_id' => $event->contract->terreno_id,
            'entity_type' => Contrato::class,
            'entity_id' => $event->contract->id,
            'action' => 'contract.signed',
            'user_id' => $event->user?->id,
            'summary' => 'Contrato assinado.',
            'payload_json' => [
                'contract_type' => $event->contract->contract_type,
                'signed_at' => $event->contract->signed_at,
            ],
            'happened_at' => now(),
        ]);
    }
}
