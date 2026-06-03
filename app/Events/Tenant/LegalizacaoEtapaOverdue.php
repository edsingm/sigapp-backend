<?php

declare(strict_types=1);

namespace App\Events\Tenant;

use App\Models\Tenant\LegalizacaoEtapa;
use Illuminate\Foundation\Events\Dispatchable;

class LegalizacaoEtapaOverdue
{
    use Dispatchable;

    public function __construct(
        public readonly LegalizacaoEtapa $etapa,
        public readonly string $tenantSlug,
    ) {}
}
