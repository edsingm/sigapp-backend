<?php

namespace App\Services\Modules;

use App\Repositories\Contracts\ModulesRepositoryInterface;

class ModulesService
{
    public function __construct(
        private readonly ModulesRepositoryInterface $repository,
    ) {}

    public function getAllModules(): array
    {
        $modules = $this->repository->activeOrdered();

        return $modules
            ->groupBy(fn ($module) => $module->sector->value)
            ->all();
    }
}
