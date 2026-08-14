<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Enums\TenantExportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AuthorizeTenantDirectorRequest;
use App\Http\Requests\Tenant\AuthorizeTenantPrivacyRequest;
use App\Http\Requests\Tenant\StorePrivacyErasureRequest;
use App\Http\Resources\Tenant\PrivacyExportResource;
use App\Models\Central\Tenant as CentralTenant;
use App\Models\Tenant\User;
use App\Services\ApiResponseService;
use App\Services\Privacy\PrivacySubjectService;
use App\Services\Privacy\TenantLifecycleService;
use App\Traits\LogsAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyController extends Controller
{
    use LogsAudit;

    public function __construct(
        private readonly PrivacySubjectService $privacy,
        private readonly TenantLifecycleService $lifecycle,
    ) {}

    public function me(AuthorizeTenantPrivacyRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponseService::unauthorized();
        }

        return ApiResponseService::success(
            $this->privacy->inventory($user),
            'PRIVACY_INVENTORY_RETRIEVED',
        );
    }

    public function storeExport(AuthorizeTenantPrivacyRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponseService::unauthorized();
        }

        $generation = $this->privacy->queueExport($user);

        $this->audit('privacy.export_requested', 'Exportação de portabilidade do titular solicitada.', [
            'export_id' => $generation->id,
            'user_id' => $user->getKey(),
        ]);

        return ApiResponseService::success(
            new PrivacyExportResource($generation),
            'PRIVACY_EXPORT_QUEUED',
            202,
        );
    }

    public function showExport(AuthorizeTenantPrivacyRequest $request, int $export): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponseService::unauthorized();
        }

        return ApiResponseService::success(
            new PrivacyExportResource($this->privacy->findExport($user, $export)),
            'PRIVACY_EXPORT_RETRIEVED',
        );
    }

    public function downloadExport(AuthorizeTenantPrivacyRequest $request, int $export): StreamedResponse|JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponseService::unauthorized();
        }

        $generation = $this->privacy->findExport($user, $export);

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
            'Content-Type' => $generation->mime_type ?? 'application/json',
        ]);
    }

    public function erase(StorePrivacyErasureRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponseService::unauthorized();
        }

        $this->privacy->erase($user, (string) $request->validated('password'));

        $this->audit('privacy.account_erased', 'Conta do titular anonimizada a pedido do próprio usuário.', [
            'user_id' => $user->getKey(),
        ]);

        return ApiResponseService::success(null, 'PRIVACY_ACCOUNT_ERASED');
    }

    public function storeWorkspaceExport(AuthorizeTenantDirectorRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponseService::unauthorized();
        }

        $generation = $this->privacy->queueWorkspaceExport($user);

        $this->audit('privacy.tenant_export_requested', 'Dump do workspace solicitado pelo admin do tenant.', [
            'export_id' => $generation->id,
        ]);

        return ApiResponseService::success(
            new PrivacyExportResource($generation),
            'PRIVACY_EXPORT_QUEUED',
            202,
        );
    }

    public function acceptAiDocumentTransfer(AuthorizeTenantDirectorRequest $request): JsonResponse
    {
        $tenant = tenancy()->tenant;
        if (! $tenant instanceof CentralTenant) {
            return ApiResponseService::error('TENANT_NOT_FOUND', 'TENANT_NOT_FOUND', null, 404);
        }

        $updated = $this->lifecycle->acceptAiDocumentTransfer($tenant);

        $this->audit('privacy.ai_document_transfer_accepted', 'Admin do tenant aceitou transferência de PDF para IA.', [
            'tenant_id' => (string) $updated->getKey(),
        ]);

        return ApiResponseService::success([
            'accepted_at' => $updated->getAttribute('ai_document_transfer_accepted_at')?->toIso8601String(),
        ], 'PRIVACY_AI_TRANSFER_ACCEPTED');
    }
}
