<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Central\PlanRolePermissionTemplate;
use App\Repositories\Contracts\PlanRolePermissionTemplateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PlanRolePermissionTemplateRepository implements PlanRolePermissionTemplateRepositoryInterface
{
    /**
     * @return Collection<int, PlanRolePermissionTemplate>
     */
    public function findByPlanId(int $planId): Collection
    {
        /** @var Collection<int, PlanRolePermissionTemplate> $templates */
        $templates = PlanRolePermissionTemplate::query()
            ->where('plan_id', $planId)
            ->orderBy('role_slug')
            ->orderBy('permission_name')
            ->get();

        return $templates;
    }
}
