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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DocumentIntelligenceService
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
        $requirements = array_map(function (array $row): DocumentRequirement {
            $requirement = new DocumentRequirement;
            $requirement->setRawAttributes($row, true);

            return $requirement;
        }, $rows);

        return $requirements;
    }

    /** @return array<int, DocumentVersion> */
    public function versions(Documento $documento): array
    {
        return $documento->versions()->latest('version')->get()->all();
    }

    public function createVersion(Documento $documento, UploadedFile $file, User $user): DocumentVersion
    {
        $checksum = hash_file('sha256', $file->getRealPath());
        if ($documento->versions()->where('checksum', $checksum)->exists()) {
            return $documento->versions()->where('checksum', $checksum)->firstOrFail();
        }

        $version = ((int) $documento->versions()->max('version')) + 1;
        $path = $file->storeAs(
            'documentos/versions/'.$documento->id,
            $version.'_'.Str::uuid().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.($file->guessExtension() ?: 'bin'),
            's3',
        );

        return $documento->versions()->create([
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
        $rows = DocumentAnalysis::query()
            ->where('documento_id', $documento->id)
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->get()
            ->toArray();
        $current = null;
        if ($rows !== []) {
            $current = new DocumentAnalysis;
            $current->setRawAttributes($rows[0], true);
        }
        if ($current !== null) {
            return $current;
        }

        $analysis = new DocumentAnalysis([
            'documento_id' => $documento->id,
            'requested_by' => $user->id,
            'status' => 'queued',
            'provider' => 'sigapp',
            'model' => null,
            'limitations' => ['A extração automática depende de um provedor OCR configurado.'],
        ]);
        $analysis->save();
        AnalyzeDocumentJob::dispatch($analysis->id);

        return $analysis;
    }

    /** @param array<string, mixed> $data */
    public function review(Documento $documento, User $user, array $data): DocumentReview
    {
        return $documento->reviews()->create([
            'reviewer_id' => $user->id,
            'status' => $data['status'],
            'valid_until' => $data['valid_until'] ?? null,
            'notes' => $data['notes'] ?? null,
            'reviewed_at' => now(),
        ]);
    }
}
