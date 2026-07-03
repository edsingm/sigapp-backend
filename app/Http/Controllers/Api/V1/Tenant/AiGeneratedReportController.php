<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Repositories\Tenant\AiGeneratedReportRepository;
use App\Repositories\Tenant\TerrenoRepository;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiGeneratedReportController extends Controller
{
    public function __construct(
        private readonly AiGeneratedReportRepository $reportRepository,
        private readonly TerrenoRepository $terrenoRepository,
    ) {}

    public function download(int $id): StreamedResponse|JsonResponse
    {
        $report = $this->reportRepository->findById($id);

        if (! $report) {
            return ApiResponseService::notFound('Relatório não encontrado.');
        }

        if ($report->terreno_id) {
            $terreno = $this->terrenoRepository->findById($report->terreno_id);

            if (! $terreno || Gate::denies('view', $terreno)) {
                return ApiResponseService::forbidden('Acesso negado ao relatório.');
            }
        } elseif ((int) $report->created_by !== (int) auth()->id()) {
            return ApiResponseService::forbidden('Acesso negado ao relatório.');
        }

        $filePath = $report->file_path;

        if (! Storage::disk('s3')->exists($filePath)) {
            return ApiResponseService::notFound('Arquivo não encontrado.');
        }

        return Storage::disk('s3')->download($filePath, $report->nome.'.pdf');
    }
}
