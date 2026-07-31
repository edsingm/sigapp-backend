<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreReportRunRequest;
use App\Http\Requests\Tenant\StoreReportScheduleRequest;
use App\Http\Requests\Tenant\StoreReportTemplateRequest;
use App\Http\Requests\Tenant\UpdateReportScheduleRequest;
use App\Http\Requests\Tenant\UpdateReportTemplateRequest;
use App\Http\Resources\Tenant\ReportRunResource;
use App\Http\Resources\Tenant\ReportScheduleResource;
use App\Http\Resources\Tenant\ReportTemplateResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\ReportCatalogService;
use App\Services\Tenant\ReportRunService;
use App\Services\Tenant\ReportScheduleService;
use App\Services\Tenant\ReportTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportBuilderController extends Controller
{
    public function __construct(
        private readonly ReportTemplateService $templates,
        private readonly ReportRunService $runs,
        private readonly ReportCatalogService $catalog,
        private readonly ReportScheduleService $schedules,
    ) {}

    public function catalog(): JsonResponse
    {
        $this->templates->ensureSystemTemplates();

        return ApiResponseService::success($this->catalog->catalog());
    }

    public function templates(Request $request): JsonResponse
    {
        return ApiResponseService::success(ReportTemplateResource::collection($this->templates->list($request->user())));
    }

    public function storeTemplate(StoreReportTemplateRequest $request): JsonResponse
    {
        return ApiResponseService::created(new ReportTemplateResource($this->templates->create($request->user(), $request->validated())));
    }

    public function showTemplate(Request $request, int $template): JsonResponse
    {
        return ApiResponseService::success(new ReportTemplateResource($this->templates->find($request->user(), $template)));
    }

    public function updateTemplate(UpdateReportTemplateRequest $request, int $template): JsonResponse
    {
        return ApiResponseService::success(new ReportTemplateResource($this->templates->update($request->user(), $template, $request->validated())));
    }

    public function destroyTemplate(Request $request, int $template): JsonResponse
    {
        $this->templates->delete($request->user(), $template);

        return ApiResponseService::noContent();
    }

    public function storeRun(StoreReportRunRequest $request): JsonResponse
    {
        return ApiResponseService::success(
            new ReportRunResource($this->runs->create($request->user(), $request->validated())),
            'Geração de relatório enfileirada com sucesso',
            202,
        );
    }

    public function showRun(Request $request, int $run): JsonResponse
    {
        return ApiResponseService::success(new ReportRunResource($this->runs->find($request->user(), $run)));
    }

    public function schedules(Request $request): JsonResponse
    {
        return ApiResponseService::success(
            ReportScheduleResource::collection($this->schedules->list($request->user())),
        );
    }

    public function storeSchedule(StoreReportScheduleRequest $request): JsonResponse
    {
        return ApiResponseService::created(
            new ReportScheduleResource($this->schedules->create($request->user(), $request->validated())),
        );
    }

    public function showSchedule(Request $request, int $schedule): JsonResponse
    {
        return ApiResponseService::success(
            new ReportScheduleResource($this->schedules->find($request->user(), $schedule)),
        );
    }

    public function updateSchedule(UpdateReportScheduleRequest $request, int $schedule): JsonResponse
    {
        return ApiResponseService::success(
            new ReportScheduleResource($this->schedules->update($request->user(), $schedule, $request->validated())),
        );
    }

    public function destroySchedule(Request $request, int $schedule): JsonResponse
    {
        $this->schedules->delete($request->user(), $schedule);

        return ApiResponseService::noContent();
    }

    public function download(Request $request, int $run): StreamedResponse|JsonResponse
    {
        $reportRun = $this->runs->find($request->user(), $run);
        if ($reportRun->status !== 'completed') {
            return ApiResponseService::conflict('O relatório ainda não está disponível.');
        }
        if ($reportRun->expires_at !== null && $reportRun->expires_at->isPast()) {
            return ApiResponseService::notFound('O relatório expirou.');
        }
        if (! is_string($reportRun->storage_disk) || ! is_string($reportRun->storage_path)) {
            return ApiResponseService::notFound('Arquivo do relatório não encontrado.');
        }
        $disk = Storage::disk($reportRun->storage_disk);
        if (! $disk->exists($reportRun->storage_path)) {
            return ApiResponseService::notFound('Arquivo do relatório não encontrado.');
        }

        $extension = $this->catalog->extensionFor($reportRun->format ?: 'csv');
        $fileName = 'relatorio-'.$reportRun->id.'.'.$extension;

        return $disk->download($reportRun->storage_path, $fileName, [
            'Content-Type' => $reportRun->mime_type ?? $this->catalog->mimeTypeFor($reportRun->format ?: 'csv'),
        ]);
    }
}
