<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RegenerateCommitteeAiDossierRequest;
use App\Http\Requests\Tenant\ShowCommitteeReviewRequest;
use App\Services\ApiResponseService;
use App\Services\Tenant\CommitteeAiDossierPdfService;
use App\Services\Tenant\CommitteeAiDossierService;
use App\Services\Tenant\CommitteeService;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class CommitteeAiDossierController extends Controller
{
    public function __construct(
        private readonly CommitteeService $committee,
        private readonly CommitteeAiDossierService $dossiers,
        private readonly CommitteeAiDossierPdfService $dossierPdf,
    ) {}

    public function show(ShowCommitteeReviewRequest $request, string $id): JsonResponse
    {
        return ApiResponseService::success(
            $this->dossiers->show($this->committee->findOrFail($id)),
        );
    }

    public function regenerate(RegenerateCommitteeAiDossierRequest $request, string $id): JsonResponse
    {
        $review = $this->committee->findOrFail($id);

        try {
            $this->dossiers->generate($review, $request->user()?->id);
        } catch (Throwable $exception) {
            return ApiResponseService::error(
                'COMMITTEE_AI_DOSSIER_FAILED',
                'Não foi possível gerar a análise da SIG IA para este comitê.',
                ['detail' => $exception->getMessage()],
                502,
            );
        }

        return ApiResponseService::success(
            $this->dossiers->show($review),
            'Análise da SIG IA atualizada com sucesso',
        );
    }

    public function exportPdf(ShowCommitteeReviewRequest $request, string $id): mixed
    {
        try {
            return $this->dossierPdf->download((int) $id);
        } catch (RuntimeException $exception) {
            return ApiResponseService::error(
                'COMMITTEE_AI_DOSSIER_PDF_UNAVAILABLE',
                $exception->getMessage(),
                null,
                409,
            );
        } catch (Throwable $exception) {
            return ApiResponseService::error(
                'COMMITTEE_AI_DOSSIER_PDF_FAILED',
                'Não foi possível exportar o dossiê em PDF.',
                ['detail' => $exception->getMessage()],
                502,
            );
        }
    }
}
