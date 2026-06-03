<?php

declare(strict_types=1);

namespace App\Events\Tenant;

use App\Models\Tenant\Projeto;
use App\Models\Tenant\User;
use Illuminate\Foundation\Events\Dispatchable;

class ProjetoFinalizado
{
    use Dispatchable;

    public function __construct(
        public readonly Projeto $projeto,
        public readonly ?User $user,
    ) {}
}
