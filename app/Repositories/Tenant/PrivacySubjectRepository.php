<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\AiDocumentChunk;
use App\Models\Tenant\DocumentAnalysis;
use App\Models\Tenant\Documento;
use App\Models\Tenant\LegalAcceptance;
use App\Models\Tenant\Terreno;

class PrivacySubjectRepository
{
    public function countTerrenosCreatedBy(int $userId): int
    {
        return Terreno::query()
            ->where('created_by', $userId)
            ->count();
    }

    public function countLegalAcceptancesByUser(int $userId): int
    {
        return LegalAcceptance::query()
            ->where('user_id', $userId)
            ->count();
    }

    /**
     * Apaga análises e chunks de documentos de identificação do terreno do titular.
     *
     * @return list<int>
     */
    public function deleteOwnerIdentityIntelligence(int $terrenoId): array
    {
        $documentIds = Documento::query()
            ->where('terreno_id', $terrenoId)
            ->whereIn('tipo', ['rg_cpf', 'procuracao'])
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($documentIds === []) {
            return [];
        }

        DocumentAnalysis::query()->whereIn('documento_id', $documentIds)->delete();
        AiDocumentChunk::query()->whereIn('document_id', $documentIds)->delete();

        return array_values($documentIds);
    }
}
