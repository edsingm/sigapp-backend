<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\ComiteAiDossier;
use App\Models\Tenant\ComiteRevisao;

class CommitteeAiDossierRepository
{
    public function findForReview(int|string $reviewId): ?ComiteAiDossier
    {
        return ComiteAiDossier::query()
            ->where('comite_revisao_id', $reviewId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertForReview(ComiteRevisao $review, array $data): ComiteAiDossier
    {
        return ComiteAiDossier::query()->updateOrCreate(
            ['comite_revisao_id' => $review->id],
            [
                'terreno_id' => $review->terreno_id,
                'viabilidade_id' => $review->viabilidade_id,
                ...$data,
            ],
        );
    }
}
