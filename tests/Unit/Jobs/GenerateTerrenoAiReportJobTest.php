<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\AiReportGenerationStatus;
use App\Jobs\GenerateTerrenoAiReportJob;
use App\Models\Tenant\AiGeneratedReport;
use App\Models\Tenant\AiReportGeneration;
use App\Models\Tenant\Terreno;
use App\Repositories\Tenant\AiReportGenerationRepository;
use App\Repositories\Tenant\TerrenoRepository;
use App\Services\Ai\Tools\CreatePdfsTool;
use App\Services\Tenant\TerrenoAiReportService;
use Laravel\Ai\Tools\Request as AiToolRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class GenerateTerrenoAiReportJobTest extends TestCase
{
    public function test_handle_marks_generation_completed_with_created_report(): void
    {
        $generation = (new AiReportGeneration)->forceFill([
            'id' => 7001,
            'terreno_id' => 42,
            'status' => AiReportGenerationStatus::QUEUED,
            'progress' => 0,
        ]);
        $terreno = (new Terreno)->forceFill(['id' => 42, 'nome' => 'Terreno de teste']);
        $generatedReport = (new AiGeneratedReport)->forceFill(['id' => 9001, 'terreno_id' => 42]);

        /** @var AiReportGenerationRepository&MockObject $generationRepository */
        $generationRepository = $this->createMock(AiReportGenerationRepository::class);
        $generationRepository->expects($this->once())->method('claimQueued')->with(7001)->willReturn($generation);
        $generationRepository->expects($this->exactly(2))
            ->method('update')
            ->with(
                $generation,
                $this->callback(static fn (array $data): bool => isset($data['status'])
                    || isset($data['progress'])
                    || isset($data['report_id'])),
            )
            ->willReturnCallback(static function (AiReportGeneration $model, array $data): AiReportGeneration {
                $model->forceFill($data);

                return $model;
            });
        $generationRepository->expects($this->never())->method('releaseForRetry');

        /** @var TerrenoRepository&MockObject $terrenoRepository */
        $terrenoRepository = $this->createMock(TerrenoRepository::class);
        $terrenoRepository->expects($this->once())->method('findById')->with(42)->willReturn($terreno);
        $terrenoRepository->expects($this->once())->method('loadDetailRelations')->with($terreno)->willReturn($terreno);

        /** @var TerrenoAiReportService&MockObject $reportService */
        $reportService = $this->createMock(TerrenoAiReportService::class);
        $reportService->expects($this->once())->method('build')->with($terreno)->willReturn([
            'title' => 'Relatório',
            'filename' => 'relatorio',
            'html_content' => '<p>Conteúdo</p>',
        ]);

        /** @var CreatePdfsTool&MockObject $pdfTool */
        $pdfTool = $this->createMock(CreatePdfsTool::class);
        $pdfTool->expects($this->once())
            ->method('handle')
            ->with($this->isInstanceOf(AiToolRequest::class))
            ->willReturn('✅ PDF gerado com sucesso!');
        $pdfTool->expects($this->once())->method('lastGeneratedReport')->willReturn($generatedReport);

        (new GenerateTerrenoAiReportJob(7001))->handle(
            $generationRepository,
            $terrenoRepository,
            $reportService,
            $pdfTool,
        );

        $this->assertSame(AiReportGenerationStatus::COMPLETED, $generation->status);
        $this->assertSame(100, $generation->progress);
        $this->assertSame(9001, $generation->report_id);
    }

    public function test_handle_skips_work_when_generation_was_not_claimed(): void
    {
        /** @var AiReportGenerationRepository&MockObject $generationRepository */
        $generationRepository = $this->createMock(AiReportGenerationRepository::class);
        $generationRepository->expects($this->once())->method('claimQueued')->with(7001)->willReturn(null);
        $generationRepository->expects($this->never())->method('update');

        /** @var TerrenoRepository&MockObject $terrenoRepository */
        $terrenoRepository = $this->createMock(TerrenoRepository::class);
        $terrenoRepository->expects($this->never())->method('findById');

        /** @var TerrenoAiReportService&MockObject $reportService */
        $reportService = $this->createMock(TerrenoAiReportService::class);
        $reportService->expects($this->never())->method('build');

        /** @var CreatePdfsTool&MockObject $pdfTool */
        $pdfTool = $this->createMock(CreatePdfsTool::class);
        $pdfTool->expects($this->never())->method('handle');

        (new GenerateTerrenoAiReportJob(7001))->handle(
            $generationRepository,
            $terrenoRepository,
            $reportService,
            $pdfTool,
        );
    }
}
