<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Central\PlanRolePermissionTemplate;
use Illuminate\Database\Eloquent\Collection;

interface PlanRolePermissionTemplateRepositoryInterface
{
    /**
     * @return Collection<int, PlanRolePermissionTemplate>
     */
    public function findByPlanId(int $planId): Collection;
}
