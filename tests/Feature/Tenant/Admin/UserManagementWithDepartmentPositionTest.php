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
use App\Models\Tenant\Position;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserManagementWithDepartmentPositionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Department $department;

    private Position $position;

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
        $this->position = Position::create(['name' => 'Analyst', 'level' => 3, 'active' => true]);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);
        $this->admin->assignRole('admin');
    }

    // --- store ---

    public function test_creates_user_with_department_and_position(): void
    {
        $payload = [
            'name' => 'New User',
            'email' => 'new@test.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'user',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/users', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.department_id', $this->department->id)
            ->assertJsonPath('data.position_id', $this->position->id);

        $this->assertDatabaseHas('users', [
            'email' => 'new@test.com',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
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
            'position_id' => $this->position->id,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/users', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id']);
    }

    public function test_creates_user_fails_without_position(): void
    {
        $payload = [
            'name' => 'No Position',
            'email' => 'noposition@test.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'user',
            'department_id' => $this->department->id,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/users', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['position_id']);
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
            'position_id' => $this->position->id,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/users', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id']);
    }

    public function test_creates_user_fails_with_nonexistent_position(): void
    {
        $payload = [
            'name' => 'Bad Position',
            'email' => 'badposition@test.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'user',
            'department_id' => $this->department->id,
            'position_id' => 9999,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/users', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['position_id']);
    }

    // --- update ---

    public function test_updates_user_with_new_department_and_position(): void
    {
        $newDepartment = Department::create(['name' => 'Finance', 'active' => true]);
        $newPosition = Position::create(['name' => 'Manager', 'level' => 2, 'active' => true]);

        $user = User::create([
            'name' => 'Existing User',
            'email' => 'existing@test.com',
            'password' => Hash::make('password'),
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);
        $user->assignRole('user');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/tenant-admin/users/{$user->id}", [
                'department_id' => $newDepartment->id,
                'position_id' => $newPosition->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.department_id', $newDepartment->id)
            ->assertJsonPath('data.position_id', $newPosition->id);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'department_id' => $newDepartment->id,
            'position_id' => $newPosition->id,
        ]);
    }

    public function test_listing_returns_department_and_position_for_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/tenant-admin/users');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $firstUser = $data[0];
        $this->assertArrayHasKey('department_id', $firstUser);
        $this->assertArrayHasKey('position_id', $firstUser);
    }

    public function test_shows_user_with_department_and_position_loaded(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/tenant-admin/users/{$this->admin->id}");

        $response->assertOk()
            ->assertJsonPath('data.department_id', $this->department->id)
            ->assertJsonPath('data.position_id', $this->position->id)
            ->assertJsonStructure([
                'data' => [
                    'department' => ['id', 'name'],
                    'position' => ['id', 'name', 'level'],
                ],
            ]);
    }

    // --- delete ---

    public function test_deletes_user_successfully(): void
    {
        $user = User::create([
            'name' => 'To Delete',
            'email' => 'todelete@test.com',
            'password' => Hash::make('password'),
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
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