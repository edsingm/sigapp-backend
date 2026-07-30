<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Enums\Common\RolesEnum;
use App\Models\Central\Entitlement;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantEntitlement;
use App\Models\Tenant\User;
use App\Services\Modules\ModuleAccessService;
use App\Services\Modules\ModulesService;
use Database\Seeders\EntitlementSeeder;
use Database\Seeders\ModulesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ModuleAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_combines_plan_rbac_module_state_and_tenant_override(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
        $this->seed(PlanSeeder::class);
        $this->seed(EntitlementSeeder::class);
        $this->seed(ModulesSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@modules.test',
            'password' => 'password',
        ]);
        $user->assignRole(RolesEnum::ADMIN);
        $plan = Plan::query()->where('slug', 'basico')->firstOrFail();
        $tenant = Tenant::query()->create([
            'name' => 'Access',
            'slug' => 'access',
            'status' => Tenant::STATUS_ACTIVE,
            'plan_id' => $plan->id,
            'admin_name' => 'Admin',
            'admin_email' => 'admin@access.test',
            'admin_password' => 'password',
        ]);
        $modules = app(ModulesService::class)->getAllModules();

        $access = app(ModuleAccessService::class)->resolve($tenant, $user, $modules);
        self::assertFalse($access['modules']['projects']['available']);
        self::assertSame(['plan'], $access['modules']['projects']['reasons']);

        $projects = Entitlement::query()->where('key', 'projects.enabled')->firstOrFail();
        TenantEntitlement::query()->create([
            'tenant_id' => $tenant->id,
            'entitlement_id' => $projects->id,
            'value' => true,
            'price' => 0,
        ]);

        $access = app(ModuleAccessService::class)->resolve($tenant, $user, $modules);
        self::assertTrue($access['features']['projects']['enabled']);
        self::assertTrue($access['modules']['projects']['available']);
        self::assertSame([], $access['modules']['projects']['reasons']);

        tenancy()->tenant = $tenant;
        tenancy()->initialized = true;
        try {
            $this->actingAs($user)
                ->withoutMiddleware()
                ->getJson('/api/v1/start')
                ->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        'tenant',
                        'user',
                        'modules',
                        'access' => [
                            'features',
                            'limits',
                            'modules' => [
                                'projects' => [
                                    'plan_enabled',
                                    'rbac_allowed',
                                    'available',
                                    'reasons',
                                ],
                            ],
                        ],
                    ],
                ])
                ->assertJsonPath('data.access.modules.projects.available', true);
        } finally {
            tenancy()->tenant = null;
            tenancy()->initialized = false;
        }
    }
}
