<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\ComiteAiDossier;
use App\Models\Tenant\ComiteRevisao;

class CommitteeAiDossierRepository
{
    private const STALE_AFTER_MINUTES = 10;

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

    /**
     * @param  array<string, mixed>  $data
     */
    public function claimForGeneration(ComiteRevisao $review, array $data): ?ComiteAiDossier
    {
        $claimed = ComiteAiDossier::query()
            ->where('comite_revisao_id', $review->id)
            ->where(function ($query): void {
                $query->whereIn('status', ['pending', 'error'])
                    ->orWhere(function ($query): void {
                        $query->where('status', 'generating')
                            ->where('updated_at', '<=', now()->subMinutes(self::STALE_AFTER_MINUTES));
                    });
            })
            ->update([
                'status' => 'generating',
                'terreno_id' => $review->terreno_id,
                'viabilidade_id' => $review->viabilidade_id,
                ...$data,
                'updated_at' => now(),
            ]);

        return $claimed === 1
            ? $this->findForReview($review->id)
            : null;
    }
}
