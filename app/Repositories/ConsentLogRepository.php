<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ConsentLog;

class ConsentLogRepository
{
    public function existsByConsentId(string $consentId): bool
    {
        return ConsentLog::query()
            ->where('consent_id', $consentId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ConsentLog
    {
        return ConsentLog::query()->create($attributes);
    }
}
