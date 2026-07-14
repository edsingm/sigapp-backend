<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\DocumentAnalysis;
use App\Models\Tenant\Documento;
use App\Models\Tenant\DocumentRequirement;
use App\Models\Tenant\DocumentReview;
use App\Models\Tenant\DocumentVersion;

class DocumentIntelligenceRepository
{
    /** @return array<int, DocumentRequirement> */
    public function requirements(string $entityType, ?int $entityId, ?string $phase): array
    {
        $query = DocumentRequirement::query()
            ->where('entity_type', $entityType)
            ->whereRaw('(entity_id IS NULL OR entity_id = ?)', [$entityId]);

        if ($phase !== null) {
            $query->where('phase', $phase);
        }

        $rows = $query
            ->where('active', true)
            ->orderBy('required', 'desc')
            ->orderBy('label')
            ->get()
            ->toArray();

        return array_map(function (array $row): DocumentRequirement {
            $requirement = new DocumentRequirement;
            $requirement->setRawAttributes($row, true);

            return $requirement;
        }, $rows);
    }

    /** @return array<int, DocumentVersion> */
    public function versions(Documento $documento): array
    {
        return $documento->versions()->latest('version')->get()->all();
    }

    public function findVersionByChecksum(Documento $documento, string $checksum): ?DocumentVersion
    {
        return $documento->versions()->where('checksum', $checksum)->first();
    }

    public function nextVersion(Documento $documento): int
    {
        return ((int) $documento->versions()->max('version')) + 1;
    }

    /** @param array<string, mixed> $data */
    public function createVersion(Documento $documento, array $data): DocumentVersion
    {
        return $documento->versions()->create($data);
    }

    public function findPendingAnalysis(Documento $documento): ?DocumentAnalysis
    {
        $rows = DocumentAnalysis::query()
            ->where('documento_id', $documento->id)
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->limit(1)
            ->get()
            ->toArray();

        if ($rows === []) {
            return null;
        }

        $analysis = new DocumentAnalysis;
        $analysis->setRawAttributes($rows[0], true);

        return $analysis;
    }

    /** @param array<string, mixed> $data */
    public function createAnalysis(array $data): DocumentAnalysis
    {
        return DocumentAnalysis::query()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function createReview(Documento $documento, array $data): DocumentReview
    {
        return $documento->reviews()->create($data);
    }
}
