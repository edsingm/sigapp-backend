<?php

declare(strict_types=1);

namespace App\Events\Tenant;

use App\Models\Tenant\LegalizacaoEtapa;
use App\Models\Tenant\User;
use Illuminate\Foundation\Events\Dispatchable;

class LegalizacaoEtapaStatusUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly LegalizacaoEtapa $etapa,
        public readonly string $status,
        public readonly ?User $user,
    ) {}
}
