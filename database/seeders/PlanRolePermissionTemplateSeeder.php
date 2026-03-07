<?php

namespace Database\Seeders;

use App\Models\Central\Plan;
use App\Models\Central\PlanRolePermissionTemplate;
use App\Services\PlanRoleMatrixTemplateService;
use Illuminate\Database\Seeder;

class PlanRolePermissionTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $service = app(PlanRoleMatrixTemplateService::class);

        Plan::query()->get()->each(function (Plan $plan) use ($service) {
            $rows = $service->rowsForPlan($plan);

            foreach ($rows as $row) {
                PlanRolePermissionTemplate::updateOrCreate(
                    [
                        'plan_id' => $row['plan_id'],
                        'role_slug' => $row['role_slug'],
                        'permission_name' => $row['permission_name'],
                    ],
                    [
                        'is_required' => $row['is_required'],
                        'is_default' => $row['is_default'],
                    ]
                );
            }
        });

        $this->command?->info('✅ Matrizes padrão de permissões por plano sincronizadas.');
    }
}
