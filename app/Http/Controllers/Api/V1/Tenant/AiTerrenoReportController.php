<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\GenerateTerrenoReportRequest;
use App\Repositories\Tenant\TerrenoRepository;
use App\Services\Ai\Tools\CreatePdfsTool;
use App\Services\ApiResponseService;
use App\Services\Tenant\TerrenoAiReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Tools\Request as AiToolRequest;

class AiTerrenoReportController extends Controller
{
    public function __construct(
        private readonly TerrenoRepository $terrenoRepository,
        private readonly TerrenoAiReportService $reportService,
        private readonly CreatePdfsTool $pdfTool,
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
        ]));

        if (! is_string($generation) || ! str_contains($generation, 'PDF gerado com sucesso')) {
            return ApiResponseService::error(
                'AI_REPORT_PDF_FAILED',
                is_string($generation) ? $generation : 'Falha ao gerar relatório em PDF.',
                null,
                500
            );
        }

        $downloadUrl = $this->buildDownloadUrl($report['filename'].'.pdf', $request);

        return ApiResponseService::success([
            'download_url' => $downloadUrl,
            'filename' => $report['filename'].'.pdf',
            'title' => $report['title'],
        ], 'Relatório do terreno gerado com sucesso.');
    }

    private function buildDownloadUrl(string $filename, Request $request): string
    {
        $relativePath = '/tenancy/assets/pdfs/'.$filename;

        $hostHeader = $request->header('X-External-Host');
        $externalHost = is_string($hostHeader) ? trim($hostHeader) : '';

        $protoHeader = $request->header('X-External-Proto');
        $externalProto = is_string($protoHeader) ? trim($protoHeader) : '';

        if ($externalHost !== '') {
            $scheme = $externalProto !== '' ? $externalProto : $request->getScheme();

            return "{$scheme}://{$externalHost}{$relativePath}";
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').$relativePath;
    }
}
