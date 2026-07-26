<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Enums\TenantExportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantExportRequest;
use App\Http\Resources\Tenant\TenantExportGenerationResource;
use App\Models\Tenant\User;
use App\Services\ApiResponseService;
use App\Services\Tenant\TenantExportGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantExportController extends Controller
{
    public function __construct(
        private readonly TenantExportGenerationService $exports,
    ) {}

    public function store(StoreTenantExportRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponseService::success(
            new TenantExportGenerationResource($this->exports->create($user, $request->validated())),
            'EXPORT_QUEUED_SUCCESSFULLY',
            202,
        );
    }

    public function show(Request $request, int $export): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponseService::success(
            new TenantExportGenerationResource($this->exports->find($user, $export)),
        );
    }

    public function download(Request $request, int $export): StreamedResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $generation = $this->exports->find($user, $export);

        if ($generation->status !== TenantExportStatus::COMPLETED) {
            return ApiResponseService::conflict('EXPORT_NOT_READY');
        }

        if ($generation->expires_at === null || $generation->expires_at->isPast()) {
            return ApiResponseService::notFound('EXPORT_EXPIRED');
        }

        if (! is_string($generation->storage_disk)
            || ! is_string($generation->storage_path)
            || ! is_string($generation->file_name)) {
            return ApiResponseService::notFound('EXPORT_FILE_NOT_FOUND');
        }

        $disk = Storage::disk($generation->storage_disk);
        if (! $disk->exists($generation->storage_path)) {
            return ApiResponseService::notFound('EXPORT_FILE_NOT_FOUND');
        }

        return $disk->download($generation->storage_path, $generation->file_name, [
            'Content-Type' => $generation->mime_type ?? 'application/octet-stream',
        ]);
    }
}
