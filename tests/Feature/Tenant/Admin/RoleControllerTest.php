<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Admin;

use App\Enums\Common\RolesEnum;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            InitializeTenancyFlexible::class,
            AddTenantContextToLogs::class,
            ApiRequestLogger::class,
            CheckSubscriptionStatus::class,
            EnsureTenantContext::class,
            EnsureTenantUser::class,
            EnsureTenantAdmin::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->adminUser = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->adminUser->assignRole('admin');
    }

    public function test_it_lists_roles_for_select(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/tenant-admin/roles/select');

        $response->assertOk()
            ->assertJsonPath('message', 'Roles recuperadas com sucesso');
    }

    public function test_it_lists_roles_with_search(): void
    {
        Role::query()->firstOrCreate(['name' => 'test-role', 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/tenant-admin/roles?search=test');

        $response->assertOk()
            ->assertJsonPath('message', 'Roles recuperadas com sucesso');
    }

    public function test_it_shows_a_role(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'show-role', 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenant-admin/roles/{$role->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'show-role');
    }

    public function test_it_returns_404_for_missing_role(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/tenant-admin/roles/99999');

        $response->assertNotFound();
    }

    public function test_it_creates_a_role_with_permissions(): void
    {
        $permission = Permission::query()->firstOrCreate(['name' => 'test.permission', 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/tenant-admin/roles', [
                'name' => 'new-role',
                'permission_ids' => [$permission->id],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'new-role');

        $this->assertDatabaseHas('roles', ['name' => 'new-role']);
    }

    public function test_it_updates_a_role(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'update-role', 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/tenant-admin/roles/{$role->id}", [
                'name' => 'updated-role',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'updated-role');
    }

    public function test_it_prevents_renaming_protected_role(): void
    {
        $role = Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/tenant-admin/roles/{$role->id}", [
                'name' => 'hacked-admin',
            ]);

        $response->assertStatus(400);
    }

    public function test_it_prevents_deleting_protected_role(): void
    {
        $role = Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/tenant-admin/roles/{$role->id}");

        $response->assertStatus(400);
    }

    public function test_it_prevents_deleting_role_with_users(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'has-users', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Assigned User',
            'email' => 'assigned@test.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/tenant-admin/roles/{$role->id}");

        $response->assertStatus(409);
    }

    public function test_it_deletes_an_empty_role(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'empty-role', 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/tenant-admin/roles/{$role->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}