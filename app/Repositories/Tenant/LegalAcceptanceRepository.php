<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Models\Tenant\LegalAcceptance;

class LegalAcceptanceRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): LegalAcceptance
    {
        return LegalAcceptance::query()->create($attributes);
    }

    /**
     * @return array<int, array{document_key: string, document_version: string, document_hash: string, accepted_at: mixed}>
     */
    public function latestByUserId(int $userId): array
    {
        return LegalAcceptance::query()
            ->where('user_id', $userId)
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->get(['document_key', 'document_version', 'document_hash', 'accepted_at'])
            ->map(static fn (LegalAcceptance $acceptance): array => [
                'document_key' => (string) $acceptance->getAttribute('document_key'),
                'document_version' => (string) $acceptance->getAttribute('document_version'),
                'document_hash' => (string) $acceptance->getAttribute('document_hash'),
                'accepted_at' => $acceptance->getAttribute('accepted_at'),
            ])
            ->values()
            ->all();
    }
}
