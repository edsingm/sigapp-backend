<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Admin;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
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
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->adminUser = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->adminUser->assignRole('admin');
    }

    public function test_it_lists_permissions_with_search(): void
    {
        Permission::firstOrCreate(['name' => 'test.permission', 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/tenant-admin/permissions?search=test');

        $response->assertOk()
            ->assertJsonPath('message', 'Permissões recuperadas com sucesso');
    }

    public function test_it_shows_a_permission(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'show.permission', 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenant-admin/permissions/{$permission->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'show.permission');
    }

    public function test_it_returns_404_for_missing_permission(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/tenant-admin/permissions/99999');

        $response->assertNotFound();
    }

    public function test_it_creates_a_permission(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/tenant-admin/permissions', [
                'name' => 'new.permission',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'new.permission');

        $this->assertDatabaseHas('permissions', ['name' => 'new.permission']);
    }

    public function test_it_updates_a_permission(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'old.permission', 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/tenant-admin/permissions/{$permission->id}", [
                'name' => 'updated.permission',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'updated.permission');
    }

    public function test_it_prevents_updating_system_permission(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'dashboard.viewer', 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/tenant-admin/permissions/{$permission->id}", [
                'name' => 'hacked.permission',
            ]);

        $response->assertStatus(400);
    }

    public function test_it_prevents_deleting_system_permission(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'dashboard.viewer', 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/tenant-admin/permissions/{$permission->id}");

        $response->assertStatus(400);
    }

    public function test_it_prevents_deleting_permission_used_by_roles(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'used.permission', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'test-role', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/tenant-admin/permissions/{$permission->id}");

        $response->assertStatus(409);
    }

    public function test_it_deletes_an_unused_permission(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'unused.permission', 'guard_name' => 'web']);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/tenant-admin/permissions/{$permission->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }
}
