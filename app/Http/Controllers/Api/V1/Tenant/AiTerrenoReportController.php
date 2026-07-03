<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\GenerateTerrenoReportRequest;
use App\Repositories\Tenant\AiGeneratedReportRepository;
use App\Repositories\Tenant\TerrenoRepository;
use App\Services\Ai\Tools\CreatePdfsTool;
use App\Services\ApiResponseService;
use App\Services\Tenant\TerrenoAiReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Tools\Request as AiToolRequest;

class AiTerrenoReportController extends Controller
{
    public function __construct(
        private readonly TerrenoRepository $terrenoRepository,
        private readonly TerrenoAiReportService $reportService,
        private readonly CreatePdfsTool $pdfTool,
        private readonly AiGeneratedReportRepository $reportRepository,
    ) {}

    public function generate(GenerateTerrenoReportRequest $request, int $id): JsonResponse
    {
        $terreno = $this->terrenoRepository->findById($id);
        if (! $terreno) {
            return ApiResponseService::notFound('Terreno não encontrado.');
        }

        $terreno = $this->terrenoRepository->loadDetailRelations($terreno);

        if (Gate::denies('view', $terreno)) {
            return ApiResponseService::forbidden('Acesso negado ao terreno.');
        }

        $report = $this->reportService->build($terreno);

        $generation = $this->pdfTool->handle(new AiToolRequest([
            'filename' => $report['filename'],
            'title' => $report['title'],
            'html_content' => $report['html_content'],
            'terreno_id' => $terreno->id,
        ]));

        if (! is_string($generation) || ! str_contains($generation, 'PDF gerado com sucesso')) {
            return ApiResponseService::error(
                'AI_REPORT_PDF_FAILED',
                is_string($generation) ? $generation : 'Falha ao gerar relatório em PDF.',
                null,
                500
            );
        }

        $generatedReport = $this->reportRepository->findLatestByTerrenoId($terreno->id);

        if (! $generatedReport) {
            return ApiResponseService::error(
                'AI_REPORT_PDF_FAILED',
                'PDF gerado, mas não foi possível localizar o registro para download.',
                null,
                500
            );
        }

        return ApiResponseService::success([
            'download_url' => route('ai.reports.download', ['id' => $generatedReport->id]),
            'filename' => $report['filename'].'.pdf',
            'title' => $report['title'],
        ], 'Relatório do terreno gerado com sucesso.');
    }
}
