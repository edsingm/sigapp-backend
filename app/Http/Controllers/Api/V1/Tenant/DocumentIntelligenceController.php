<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DocumentAnalysisRequest;
use App\Http\Requests\Tenant\DocumentReviewRequest;
use App\Http\Requests\Tenant\DocumentVersionRequest;
use App\Http\Requests\Tenant\ListDocumentRequirementsRequest;
use App\Http\Resources\Tenant\DocumentAnalysisResource;
use App\Http\Resources\Tenant\DocumentRequirementResource;
use App\Http\Resources\Tenant\DocumentReviewResource;
use App\Http\Resources\Tenant\DocumentVersionResource;
use App\Models\Tenant\Documento;
use App\Repositories\Tenant\DocumentoRepository;
use App\Services\ApiResponseService;
use App\Services\Tenant\DocumentIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class DocumentIntelligenceController extends Controller
{
    public function __construct(
        private readonly DocumentoRepository $documents,
        private readonly DocumentIntelligenceService $service,
    ) {}

    public function requirements(ListDocumentRequirementsRequest $request): JsonResponse
    {
        $requirements = $this->service->requirements(
            (string) $request->validated('entity_type'),
            $request->integer('entity_id') ?: null,
            $request->validated('phase'),
        );

        return ApiResponseService::success(DocumentRequirementResource::collection($requirements));
    }

    public function versions(int $documento): JsonResponse
    {
        $document = $this->findAndAuthorize($documento, 'view');

        return ApiResponseService::success(DocumentVersionResource::collection($this->service->versions($document)));
    }

    public function storeVersion(DocumentVersionRequest $request, int $documento): JsonResponse
    {
        $document = $this->findAndAuthorize($documento, 'update');
        $file = $request->file('arquivo');
        if (! $file instanceof UploadedFile) {
            return ApiResponseService::validationError(['arquivo' => ['Arquivo inválido.']]);
        }

        return ApiResponseService::created(new DocumentVersionResource($this->service->createVersion($document, $file, $request->user())));
    }

    public function analysis(DocumentAnalysisRequest $request, int $documento): JsonResponse
    {
        $document = $this->findAndAuthorize($documento, 'view');
        $analysis = $document->analyses()->latest('id')->first();

        return ApiResponseService::success($analysis ? new DocumentAnalysisResource($analysis) : null);
    }

    public function requestAnalysis(DocumentAnalysisRequest $request, int $documento): JsonResponse
    {
        $document = $this->findAndAuthorize($documento, 'view');
        $force = (bool) ($request->validated('force') ?? false);

        return ApiResponseService::success(
            new DocumentAnalysisResource($this->service->requestAnalysis($document, $request->user(), $force)),
            'Análise documental enfileirada com sucesso',
            202
        );
    }

    public function review(DocumentReviewRequest $request, int $documento): JsonResponse
    {
        $document = $this->findAndAuthorize($documento, 'update');

        return ApiResponseService::created(new DocumentReviewResource($this->service->review($document, $request->user(), $request->validated())));
    }

    private function findAndAuthorize(int $id, string $ability): Documento
    {
        $document = $this->documents->findOrFail($id);
        Gate::authorize($ability, $document);

        return $document;
    }
}
