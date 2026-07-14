<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tenant;

use App\Enums\AiReportGenerationStatus;
use App\Jobs\GenerateTerrenoAiReportJob;
use App\Models\Tenant\AiReportGeneration;
use App\Models\Tenant\Terreno;
use App\Repositories\Tenant\AiReportGenerationRepository;
use App\Services\Tenant\AiReportGenerationService;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class AiReportGenerationServiceTest extends TestCase
{
    public function test_queue_creates_generation_and_dispatches_job(): void
    {
        Queue::fake();

        $terreno = (new Terreno)->forceFill(['id' => 42]);
        $generation = (new AiReportGeneration)->forceFill([
            'id' => 7001,
            'terreno_id' => 42,
            'status' => AiReportGenerationStatus::QUEUED,
            'progress' => 0,
        ]);

        /** @var AiReportGenerationRepository&MockObject $repository */
        $repository = $this->createMock(AiReportGenerationRepository::class);
        $repository->expects($this->once())
            ->method('create')
            ->with($this->callback(static fn (array $data): bool => $data['terreno_id'] === 42
                && $data['requested_by'] === 9
                && $data['status'] === AiReportGenerationStatus::QUEUED
                && $data['progress'] === 0))
            ->willReturn($generation);

        $result = (new AiReportGenerationService($repository))->queue($terreno, 9);

        $this->assertSame($generation, $result);
        Queue::assertPushed(GenerateTerrenoAiReportJob::class, static fn (GenerateTerrenoAiReportJob $job): bool => $job->generationId === 7001);
    }
}
