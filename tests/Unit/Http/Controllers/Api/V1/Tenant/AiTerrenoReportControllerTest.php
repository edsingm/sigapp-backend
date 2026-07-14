<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api\V1\Tenant;

use App\Enums\AiReportGenerationStatus;
use App\Http\Controllers\Api\V1\Tenant\AiTerrenoReportController;
use App\Http\Requests\Tenant\GenerateTerrenoReportRequest;
use App\Models\Tenant\AiGeneratedReport;
use App\Models\Tenant\AiReportGeneration;
use App\Models\Tenant\Terreno;
use App\Repositories\Tenant\TerrenoRepository;
use App\Services\Ai\Tools\CreatePdfsTool;
use App\Services\Tenant\AiReportGenerationService;
use App\Services\Tenant\TerrenoAiReportService;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Tools\Request as AiToolRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class AiTerrenoReportControllerTest extends TestCase
{
    public function test_generate_returns_the_report_created_by_current_pdf_call(): void
    {
        $terreno = (new Terreno)->forceFill([
            'id' => 42,
            'nome' => 'Terreno de teste',
        ]);
        $createdReport = (new AiGeneratedReport)->forceFill([
            'id' => 9001,
            'terreno_id' => 42,
            'nome' => 'Relatório de teste',
        ]);

        /** @var TerrenoRepository&MockObject $terrenoRepository */
        $terrenoRepository = $this->createMock(TerrenoRepository::class);
        $terrenoRepository->expects($this->once())->method('findById')->with(42)->willReturn($terreno);
        $terrenoRepository->expects($this->once())->method('loadDetailRelations')->with($terreno)->willReturn($terreno);

        /** @var TerrenoAiReportService&MockObject $reportService */
        $reportService = $this->createMock(TerrenoAiReportService::class);
        $reportService->expects($this->once())->method('build')->with($terreno)->willReturn([
            'title' => 'Relatório SIG IA do Terreno #42',
            'filename' => 'relatorio-sig-ia-terreno-42-terreno-de-teste',
            'html_content' => '<p>Conteúdo</p>',
        ]);

        /** @var CreatePdfsTool&MockObject $pdfTool */
        $pdfTool = $this->createMock(CreatePdfsTool::class);
        $pdfTool->expects($this->once())
            ->method('handle')
            ->with($this->isInstanceOf(AiToolRequest::class))
            ->willReturn('✅ PDF gerado com sucesso!');
        $pdfTool->expects($this->once())->method('lastGeneratedReport')->willReturn($createdReport);

        /** @var AiReportGenerationService&MockObject $generationService */
        $generationService = $this->createMock(AiReportGenerationService::class);

        Gate::shouldReceive('denies')->once()->with('view', $terreno)->andReturnFalse();

        $controller = new AiTerrenoReportController($terrenoRepository, $reportService, $pdfTool, $generationService);
        $response = $controller->generate(new GenerateTerrenoReportRequest, 42);

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true)['data'];
        $this->assertStringContainsString('/api/v1/ai/reports/9001/download', $data['download_url']);
        $this->assertSame('relatorio-sig-ia-terreno-42-terreno-de-teste.pdf', $data['filename']);
    }

    public function test_generate_rejects_a_report_for_another_terrain(): void
    {
        $terreno = (new Terreno)->forceFill(['id' => 42, 'nome' => 'Terreno de teste']);
        $wrongReport = (new AiGeneratedReport)->forceFill(['id' => 9002, 'terreno_id' => 99]);

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
        $pdfTool->expects($this->once())->method('handle')->willReturn('✅ PDF gerado com sucesso!');
        $pdfTool->expects($this->once())->method('lastGeneratedReport')->willReturn($wrongReport);

        /** @var AiReportGenerationService&MockObject $generationService */
        $generationService = $this->createMock(AiReportGenerationService::class);

        Gate::shouldReceive('denies')->once()->with('view', $terreno)->andReturnFalse();

        $controller = new AiTerrenoReportController($terrenoRepository, $reportService, $pdfTool, $generationService);
        $response = $controller->generate(new GenerateTerrenoReportRequest, 42);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('AI_REPORT_PDF_FAILED', $response->getData(true)['error']['code']);
    }

    public function test_generate_async_returns_202_with_queued_generation(): void
    {
        $terreno = (new Terreno)->forceFill(['id' => 42, 'nome' => 'Terreno de teste']);
        $generation = (new AiReportGeneration)->forceFill([
            'id' => 7001,
            'terreno_id' => 42,
            'status' => AiReportGenerationStatus::QUEUED,
            'progress' => 0,
            'requested_at' => now(),
        ]);

        /** @var TerrenoRepository&MockObject $terrenoRepository */
        $terrenoRepository = $this->createMock(TerrenoRepository::class);
        $terrenoRepository->expects($this->once())->method('findById')->with(42)->willReturn($terreno);

        /** @var TerrenoAiReportService&MockObject $reportService */
        $reportService = $this->createMock(TerrenoAiReportService::class);
        /** @var CreatePdfsTool&MockObject $pdfTool */
        $pdfTool = $this->createMock(CreatePdfsTool::class);
        /** @var AiReportGenerationService&MockObject $generationService */
        $generationService = $this->createMock(AiReportGenerationService::class);
        $generationService->expects($this->once())->method('queue')->with($terreno, 0)->willReturn($generation);

        Gate::shouldReceive('denies')->once()->with('view', $terreno)->andReturnFalse();

        $controller = new AiTerrenoReportController($terrenoRepository, $reportService, $pdfTool, $generationService);
        $response = $controller->generateAsync(new GenerateTerrenoReportRequest, 42);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('queued', $response->getData(true)['data']['status']);
        $this->assertSame(7001, $response->getData(true)['data']['id']);
    }

    public function test_status_returns_generation_for_authorized_terrain(): void
    {
        $terreno = (new Terreno)->forceFill(['id' => 42, 'nome' => 'Terreno de teste']);
        $generation = (new AiReportGeneration)->forceFill([
            'id' => 7002,
            'terreno_id' => 42,
            'status' => AiReportGenerationStatus::COMPLETED,
            'progress' => 100,
            'report_id' => 9001,
            'completed_at' => now(),
        ]);

        /** @var TerrenoRepository&MockObject $terrenoRepository */
        $terrenoRepository = $this->createMock(TerrenoRepository::class);
        $terrenoRepository->expects($this->once())->method('findById')->with(42)->willReturn($terreno);

        /** @var TerrenoAiReportService&MockObject $reportService */
        $reportService = $this->createMock(TerrenoAiReportService::class);
        /** @var CreatePdfsTool&MockObject $pdfTool */
        $pdfTool = $this->createMock(CreatePdfsTool::class);
        /** @var AiReportGenerationService&MockObject $generationService */
        $generationService = $this->createMock(AiReportGenerationService::class);
        $generationService->expects($this->once())->method('findForTerreno')->with(7002, 42)->willReturn($generation);

        Gate::shouldReceive('denies')->once()->with('view', $terreno)->andReturnFalse();

        $controller = new AiTerrenoReportController($terrenoRepository, $reportService, $pdfTool, $generationService);
        $response = $controller->status(42, 7002);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('completed', $response->getData(true)['data']['status']);
        $this->assertStringContainsString('/api/v1/ai/reports/9001/download', $response->getData(true)['data']['download_url']);
    }
}
