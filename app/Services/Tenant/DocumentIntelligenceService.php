<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Exceptions\DocumentAnalysisUnsupportedException;
use App\Jobs\AnalyzeDocumentJob;
use App\Models\Central\Tenant;
use App\Models\Tenant\DocumentAnalysis;
use App\Models\Tenant\Documento;
use App\Models\Tenant\DocumentRequirement;
use App\Models\Tenant\DocumentReview;
use App\Models\Tenant\DocumentVersion;
use App\Models\Tenant\User;
use App\Repositories\Tenant\DocumentIntelligenceRepository;
use App\Services\PlanMatrixService;
use App\Support\SafeUploadExtension;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DocumentIntelligenceService
{
    public function __construct(
        private readonly DocumentIntelligenceRepository $repository,
        private readonly StorageQuotaService $storageQuota,
        private readonly DocumentAnalysisEligibility $eligibility,
        private readonly PlanMatrixService $planMatrix,
    ) {}

    /** @return array<int, DocumentRequirement> */
    public function requirements(string $entityType, ?int $entityId, ?string $phase): array
    {
        return $this->repository->requirements($entityType, $entityId, $phase);
    }

    /** @return array<int, DocumentVersion> */
    public function versions(Documento $documento): array
    {
        return $this->repository->versions($documento);
    }

    public function createVersion(Documento $documento, UploadedFile $file, User $user): DocumentVersion
    {
        $checksum = (string) hash_file('sha256', $file->getRealPath());
        $existingVersion = $this->repository->findVersionByChecksum($documento, $checksum);
        if ($existingVersion !== null) {
            return $existingVersion;
        }

        $extension = SafeUploadExtension::resolve($file, SafeUploadExtension::DOCUMENT_EXTENSIONS);
        if ($extension === null) {
            throw ValidationException::withMessages([
                'arquivo' => ['Tipo de conteúdo não reconhecido ou não permitido. Envie PDF, imagens, Office, KML/KMZ ou DWG.'],
            ]);
        }

        $version = $this->repository->nextVersion($documento);
        $path = $file->storeAs(
            'documentos/versions/'.$documento->id,
            $version.'_'.Str::uuid().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$extension,
            's3',
        );
        if (! is_string($path)) {
            throw new RuntimeException('Não foi possível armazenar a versão do documento.');
        }

        return $this->storageQuota->commitFile(
            's3',
            $path,
            fn (int $size): DocumentVersion => $this->repository->createVersion($documento, [
                'version' => $version,
                'file_path' => $path,
                'disk' => 's3',
                'mime_type' => $file->getMimeType(),
                'size' => $size,
                'checksum' => $checksum,
                'created_by' => $user->id,
                'metadata' => ['original_name' => $file->getClientOriginalName()],
            ]),
        );
    }

    public function requestAnalysis(Documento $documento, User $user, bool $force = false): DocumentAnalysis
    {
        if (! $this->eligibility->canAnalyzeOnDemand($documento)) {
            throw new DocumentAnalysisUnsupportedException;
        }

        $current = $this->repository->findPendingAnalysis($documento);
        if ($current !== null) {
            return $current;
        }

        // Sem force: reutiliza a última completed (evita custo). Com force ou só failed: nova análise.
        if (! $force) {
            $latestCompleted = $this->repository->findLatestCompletedAnalysis($documento);
            if ($latestCompleted instanceof DocumentAnalysis) {
                return $latestCompleted;
            }
        }

        $analysis = $this->repository->createAnalysis([
            'documento_id' => $documento->id,
            'requested_by' => $user->id,
            'status' => 'queued',
            'provider' => (string) config('ai.document_provider', 'opencode_go'),
            'model' => (string) config('ai.document_model', 'gpt-5.6-luna'),
            'limitations' => [],
        ]);

        // Dispatch após criar o registro. Em QUEUE_CONNECTION=sync o job roda já;
        // AnalyzeDocumentJob isola falhas de embedding para não quebrar o caller (chat).
        AnalyzeDocumentJob::dispatch($analysis->id);

        // Recarrega status caso o job sync já tenha completado/falhado.
        return $analysis->fresh() ?? $analysis;
    }

    /**
     * Enfileira análise automática se elegível (allowlist + PDF + feature).
     * Não lança se o tipo não for elegível; não bloqueia o upload.
     */
    public function dispatchAutoAnalysisIfEligible(Documento $documento, User $user): ?DocumentAnalysis
    {
        if (! $this->eligibility->shouldAutoAnalyze($documento)) {
            return null;
        }

        $tenant = tenant();
        if (! $tenant instanceof Tenant || ! $this->planMatrix->hasFeatureForTenant($tenant, 'documents.intelligence')) {
            return null;
        }

        try {
            return $this->requestAnalysis($documento, $user);
        } catch (DocumentAnalysisUnsupportedException) {
            return null;
        }
    }

    /** @param array<string, mixed> $data */
    public function review(Documento $documento, User $user, array $data): DocumentReview
    {
        return $this->repository->createReview($documento, [
            'reviewer_id' => $user->id,
            'status' => $data['status'],
            'valid_until' => $data['valid_until'] ?? null,
            'notes' => $data['notes'] ?? null,
            'reviewed_at' => now(),
        ]);
    }
}
