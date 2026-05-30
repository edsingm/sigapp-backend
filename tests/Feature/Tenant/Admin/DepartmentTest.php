<?php

namespace Tests\Feature\Tenant\Admin;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Models\Tenant\Department;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_lists_departments(): void
    {
        Department::create(['name' => 'Engineering', 'active' => true]);
        Department::create(['name' => 'Sales', 'active' => true]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/tenant-admin/departments');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_creates_department_with_valid_data(): void
    {
        $payload = [
            'name' => 'IT',
            'active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/departments', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'IT');

        $this->assertDatabaseHas('departments', ['name' => 'IT']);
    }

    public function test_creates_department_fails_without_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/departments', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_shows_department(): void
    {
        $department = Department::create(['name' => 'Finance', 'active' => true]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/tenant-admin/departments/{$department->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Finance');
    }

    public function test_show_inexistent_department_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/tenant-admin/departments/9999');

        $response->assertNotFound();
    }

    public function test_updates_department(): void
    {
        $department = Department::create(['name' => 'Old Name', 'active' => true]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/tenant-admin/departments/{$department->id}", [
                'name' => 'New Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'New Name']);
    }

    public function test_deletes_department_without_users(): void
    {
        $department = Department::create(['name' => 'Empty', 'active' => true]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/tenant-admin/departments/{$department->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }

    public function test_cannot_delete_department_with_assigned_users(): void
    {
        $department = Department::create(['name' => 'With User', 'active' => true]);

        User::create([
            'name' => 'Assigned User',
            'email' => 'assigned@test.com',
            'password' => Hash::make('password'),
            'department_id' => $department->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/tenant-admin/departments/{$department->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'DEPARTMENT_IN_USE');
    }

    public function test_lists_active_departments_for_select(): void
    {
        Department::create(['name' => 'Active', 'active' => true]);
        Department::create(['name' => 'Inactive', 'active' => false]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/tenant-admin/departments/select');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Active', $data[0]['name']);
    }
}