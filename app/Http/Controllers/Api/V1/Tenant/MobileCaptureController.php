<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Exceptions\MobileCaptureConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CommitMobileCaptureRequest;
use App\Http\Requests\Tenant\MobileCaptureAttachmentRequest;
use App\Http\Requests\Tenant\MobileCaptureRequest;
use App\Http\Requests\Tenant\MobileCaptureStatusRequest;
use App\Http\Requests\Tenant\UpdateMobileCaptureRequest;
use App\Http\Resources\Tenant\MobileCaptureResource;
use App\Services\ApiResponseService;
use App\Services\Tenant\MobileCaptureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MobileCaptureController extends Controller
{
    public function __construct(private readonly MobileCaptureService $service) {}

    public function store(MobileCaptureRequest $request): JsonResponse
    {
        return ApiResponseService::success(
            new MobileCaptureResource($this->service->createOrUpdate($request->user(), $request->validated())),
            'Captura mobile sincronizada com sucesso',
        );
    }

    public function update(UpdateMobileCaptureRequest $request, string $clientId): JsonResponse
    {
        return $this->runWithConflict(fn () => ApiResponseService::success(
            new MobileCaptureResource($this->service->update($request->user(), $clientId, $request->validated())),
            'Rascunho mobile atualizado com sucesso',
        ));
    }

    public function attachment(MobileCaptureAttachmentRequest $request, string $clientId): JsonResponse
    {
        $file = $request->file('arquivo');
        if (! $file instanceof UploadedFile) {
            return ApiResponseService::validationError(['arquivo' => ['Arquivo inválido.']]);
        }

        return ApiResponseService::success(
            new MobileCaptureResource($this->service->upload($request->user(), $clientId, $file)),
            'Anexo mobile recebido com sucesso',
        );
    }

    public function commit(CommitMobileCaptureRequest $request, string $clientId): JsonResponse
    {
        return $this->runWithConflict(fn () => ApiResponseService::success(
            new MobileCaptureResource($this->service->commit($request->user(), $clientId, (int) $request->validated('base_version'))),
            'Captura mobile consolidada com sucesso',
        ));
    }

    public function status(MobileCaptureStatusRequest $request, string $clientId): JsonResponse
    {
        return ApiResponseService::success(new MobileCaptureResource($this->service->status($request->user(), $clientId)));
    }

    /** @param callable(): JsonResponse $callback */
    private function runWithConflict(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (MobileCaptureConflictException $exception) {
            return ApiResponseService::error('CAPTURE_CONFLICT', $exception->getMessage(), $exception->details, 409);
        } catch (RuntimeException $exception) {
            Log::warning('Falha ao sincronizar captura mobile.', ['message' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
