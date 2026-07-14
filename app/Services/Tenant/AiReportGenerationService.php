<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\AiReportGenerationStatus;
use App\Jobs\GenerateTerrenoAiReportJob;
use App\Models\Tenant\AiReportGeneration;
use App\Models\Tenant\Terreno;
use App\Repositories\Tenant\AiReportGenerationRepository;

class AiReportGenerationService
{
    public function __construct(
        private readonly AiReportGenerationRepository $repository,
    ) {}

    public function queue(Terreno $terreno, ?int $requestedBy): AiReportGeneration
    {
        $generation = $this->repository->create([
            'terreno_id' => $terreno->id,
            'requested_by' => $requestedBy,
            'status' => AiReportGenerationStatus::QUEUED,
            'progress' => 0,
            'requested_at' => now(),
        ]);

        GenerateTerrenoAiReportJob::dispatch($generation->id)->afterCommit();

        return $generation;
    }

    public function findForTerreno(int $id, int $terrenoId): ?AiReportGeneration
    {
        return $this->repository->findForTerreno($id, $terrenoId);
    }
}
