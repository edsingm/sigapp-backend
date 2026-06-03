<?php

declare(strict_types=1);

namespace App\Events\Tenant;

use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use App\Models\Tenant\Viabilidade;
use Illuminate\Foundation\Events\Dispatchable;

class ViabilidadeSubmitted
{
    use Dispatchable;

    public function __construct(
        public readonly Viabilidade $viabilidade,
        public readonly Terreno $terreno,
        public readonly ?User $actor,
    ) {}
}
