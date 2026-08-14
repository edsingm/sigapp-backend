<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Central\LegalAcceptance;

class CentralLegalAcceptanceRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): LegalAcceptance
    {
        return LegalAcceptance::query()->create($attributes);
    }
}
