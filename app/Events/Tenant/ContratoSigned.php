<?php

declare(strict_types=1);

namespace App\Events\Tenant;

use App\Models\Tenant\Contrato;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Events\Dispatchable;

class ContratoSigned
{
    use Dispatchable;

    public function __construct(
        public readonly Contrato $contract,
        public readonly Terreno $terreno,
        public readonly ?User $user,
    ) {}
}
