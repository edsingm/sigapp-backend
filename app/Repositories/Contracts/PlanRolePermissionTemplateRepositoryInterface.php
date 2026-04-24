<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface PlanRolePermissionTemplateRepositoryInterface
{
    /**
     * @return Collection<int, \App\Models\Central\PlanRolePermissionTemplate>
     */
    public function findByPlanId(int $planId): Collection;
}
