<?php

namespace Tests\Feature\Tenant\Admin;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\Department;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserManagementWithDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Department $department;

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
        Role::query()->firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $this->department = Department::create(['name' => 'Engineering', 'active' => true]);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'department_id' => $this->department->id,
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_creates_user_with_department(): void
    {
        $payload = [
            'name' => 'New User',
            'email' => 'new@test.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'user',
            'department_id' => $this->department->id,
            'status' => 'Suspended',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/users', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.department_id', $this->department->id)
            ->assertJsonPath('data.status', 'Suspended');

        $this->assertArrayNotHasKey('position_id', $response->json('data'));
        $this->assertArrayNotHasKey('position', $response->json('data'));

        $this->assertDatabaseHas('users', [
            'email' => 'new@test.com',
            'department_id' => $this->department->id,
            'status' => 'Suspended',
        ]);
    }

    public function test_creates_user_fails_without_department(): void
    {
        $payload = [
            'name' => 'No Dept',
            'email' => 'nodept@test.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'user',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/users', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id']);
    }

    public function test_creates_user_fails_with_nonexistent_department(): void
    {
        $payload = [
            'name' => 'Bad Dept',
            'email' => 'baddept@test.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'user',
            'department_id' => 9999,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/users', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id']);
    }

    public function test_updates_user_with_new_department(): void
    {
        $newDepartment = Department::create(['name' => 'Finance', 'active' => true]);

        $user = User::create([
            'name' => 'Existing User',
            'email' => 'existing@test.com',
            'password' => Hash::make('password'),
            'department_id' => $this->department->id,
        ]);
        $user->assignRole('user');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/tenant-admin/users/{$user->id}", [
                'department_id' => $newDepartment->id,
                'status' => 'Inactive',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.department_id', $newDepartment->id)
            ->assertJsonPath('data.status', 'Inactive');

        $this->assertArrayNotHasKey('position_id', $response->json('data'));
        $this->assertArrayNotHasKey('position', $response->json('data'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'department_id' => $newDepartment->id,
            'status' => 'Inactive',
        ]);
    }

    public function test_listing_returns_department_for_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/tenant-admin/users');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $firstUser = $data[0];
        $this->assertArrayHasKey('department_id', $firstUser);
        $this->assertArrayHasKey('status', $firstUser);
        $this->assertArrayNotHasKey('position_id', $firstUser);
        $this->assertArrayNotHasKey('position', $firstUser);
    }

    public function test_tenant_user_schema_does_not_keep_positions(): void
    {
        $this->assertFalse(Schema::hasTable('positions'));
        $this->assertFalse(Schema::hasColumn('users', 'position_id'));
    }

    public function test_shows_user_with_department_loaded(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/tenant-admin/users/{$this->admin->id}");

        $response->assertOk()
            ->assertJsonPath('data.department_id', $this->department->id)
            ->assertJsonPath('data.status', 'Active')
            ->assertJsonStructure([
                'data' => [
                    'department' => ['id', 'name'],
                ],
            ]);

        $this->assertArrayNotHasKey('position_id', $response->json('data'));
        $this->assertArrayNotHasKey('position', $response->json('data'));
    }

    public function test_deletes_user_successfully(): void
    {
        $user = User::create([
            'name' => 'To Delete',
            'email' => 'todelete@test.com',
            'password' => Hash::make('password'),
            'department_id' => $this->department->id,
        ]);
        $user->assignRole('user');

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/tenant-admin/users/{$user->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_unauthorized_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/tenant-admin/users', [
            'name' => 'Unauthorized',
            'email' => 'unauth@test.com',
        ]);

        $response->assertUnauthorized();
    }
}
