<?php

namespace Tests\Feature\Tenant\Admin;

use App\Models\Tenant\Position;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PositionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->admin->assignRole('admin');
    }

    public function test_lists_positions(): void
    {
        Position::create(['name' => 'Director', 'level' => 1]);
        Position::create(['name' => 'Manager', 'level' => 2]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/tenant-admin/positions');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_creates_position_with_valid_data(): void
    {
        $payload = [
            'name' => 'Analyst',
            'level' => 3,
            'active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/positions', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Analyst')
            ->assertJsonPath('data.level', 3);

        $this->assertDatabaseHas('positions', ['name' => 'Analyst', 'level' => 3]);
    }

    public function test_creates_position_fails_without_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/positions', ['level' => 1]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_creates_position_fails_without_level(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/positions', ['name' => 'Position']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['level']);
    }

    public function test_creates_position_fails_with_invalid_level(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/tenant-admin/positions', ['name' => 'Position', 'level' => 0]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['level']);
    }

    public function test_shows_position(): void
    {
        $position = Position::create(['name' => 'CEO', 'level' => 1]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/tenant-admin/positions/{$position->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'CEO')
            ->assertJsonPath('data.level', 1);
    }

    public function test_show_inexistent_position_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/tenant-admin/positions/9999');

        $response->assertNotFound();
    }

    public function test_updates_position(): void
    {
        $position = Position::create(['name' => 'Junior', 'level' => 5]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/tenant-admin/positions/{$position->id}", [
                'level' => 4,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.level', 4);

        $this->assertDatabaseHas('positions', ['id' => $position->id, 'level' => 4]);
    }

    public function test_deletes_position_without_users(): void
    {
        $position = Position::create(['name' => 'No Users', 'level' => 10]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/tenant-admin/positions/{$position->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('positions', ['id' => $position->id]);
    }

    public function test_cannot_delete_position_with_assigned_users(): void
    {
        $position = Position::create(['name' => 'With User', 'level' => 2]);

        User::create([
            'name' => 'Assigned User',
            'email' => 'assigned@test.com',
            'password' => Hash::make('password'),
            'position_id' => $position->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/tenant-admin/positions/{$position->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error', 'POSITION_IN_USE');
    }

    public function test_lists_active_positions_for_select(): void
    {
        Position::create(['name' => 'Active', 'level' => 1, 'active' => true]);
        Position::create(['name' => 'Inactive', 'level' => 2, 'active' => false]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/tenant-admin/positions/select');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Active', $data[0]['name']);
    }
}
