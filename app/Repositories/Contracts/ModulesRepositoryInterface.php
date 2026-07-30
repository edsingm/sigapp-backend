<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Central\Modules\Modules;
use Illuminate\Database\Eloquent\Collection;

interface ModulesRepositoryInterface
{
    /** @return Collection<int, Modules> */
    public function activeOrdered(): Collection;
}
