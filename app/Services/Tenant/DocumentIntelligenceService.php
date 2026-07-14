<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Jobs\AnalyzeDocumentJob;
use App\Models\Tenant\DocumentAnalysis;
use App\Models\Tenant\Documento;
use App\Models\Tenant\DocumentRequirement;
use App\Models\Tenant\DocumentReview;
use App\Models\Tenant\DocumentVersion;
use App\Models\Tenant\User;
use App\Repositories\Tenant\DocumentIntelligenceRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DocumentIntelligenceService
{
    public function __construct(
        private readonly DocumentIntelligenceRepository $repository,
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

        $version = $this->repository->nextVersion($documento);
        $path = $file->storeAs(
            'documentos/versions/'.$documento->id,
            $version.'_'.Str::uuid().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.($file->guessExtension() ?: 'bin'),
            's3',
        );

        return $this->repository->createVersion($documento, [
            'version' => $version,
            'file_path' => $path,
            'disk' => 's3',
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => $checksum,
            'created_by' => $user->id,
            'metadata' => ['original_name' => $file->getClientOriginalName()],
        ]);
    }

    public function requestAnalysis(Documento $documento, User $user): DocumentAnalysis
    {
        $current = $this->repository->findPendingAnalysis($documento);
        if ($current !== null) {
            return $current;
        }

        $analysis = $this->repository->createAnalysis([
            'documento_id' => $documento->id,
            'requested_by' => $user->id,
            'status' => 'queued',
            'provider' => 'sigapp',
            'model' => null,
            'limitations' => ['A extração automática depende de um provedor OCR configurado.'],
        ]);
        AnalyzeDocumentJob::dispatch($analysis->id);

        return $analysis;
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
