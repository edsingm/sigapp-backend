<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ConsentLog;

class ConsentLogRepository
{
    /**
     * Cria ou atualiza o registro de consentimento pelo consent_id.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrCreateByConsentId(string $consentId, array $attributes): ConsentLog
    {
        return ConsentLog::query()->updateOrCreate(
            ['consent_id' => $consentId],
            $attributes,
        );
    }
}
