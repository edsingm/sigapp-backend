<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Central\Modules\Modules;
use App\Repositories\Contracts\ModulesRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ModulesRepository implements ModulesRepositoryInterface
{
    public function activeOrdered(): Collection
    {
        return Modules::all()
            ->where('active', true)
            ->sortBy('order')
            ->values();
    }
}
