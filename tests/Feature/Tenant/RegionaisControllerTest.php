<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\Regional;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegionaisControllerTest extends TestCase
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
            \App\Http\Middleware\CheckFeature::class,
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

    public function test_it_lists_regionais(): void
    {
        Regional::create(['nome' => 'Regional A', 'cidade' => 'Cidade A', 'estado' => 'SP', 'created_by' => $this->adminUser->id]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/regionais');

        $response->assertOk()
            ->assertJsonPath('message', 'Regionais recuperadas com sucesso');
    }

    public function test_it_shows_a_regional(): void
    {
        $regional = Regional::create(['nome' => 'Regional B', 'cidade' => 'Cidade B', 'estado' => 'RJ', 'created_by' => $this->adminUser->id]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/regionais/{$regional->id}");

        $response->assertOk()
            ->assertJsonPath('data.nome', 'Regional B');
    }

    public function test_it_returns_404_for_missing_regional(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/regionais/99999');

        $response->assertNotFound();
    }

    public function test_it_creates_a_regional(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/regionais', [
                'nome' => 'Nova Regional',
                'cidade' => 'Nova Cidade',
                'estado' => 'MG',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.nome', 'Nova Regional');

        $this->assertDatabaseHas('regionais', ['nome' => 'Nova Regional']);
    }

    public function test_it_updates_a_regional(): void
    {
        $regional = Regional::create(['nome' => 'Old', 'cidade' => 'Old City', 'estado' => 'SP', 'created_by' => $this->adminUser->id]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/regionais/{$regional->id}", [
                'nome' => 'Updated',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.nome', 'Updated');
    }

    public function test_it_deletes_a_regional(): void
    {
        $regional = Regional::create(['nome' => 'To Delete', 'cidade' => 'City', 'estado' => 'SP', 'created_by' => $this->adminUser->id]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/regionais/{$regional->id}");

        $response->assertOk();
        $this->assertSoftDeleted('regionais', ['id' => $regional->id]);
    }

    public function test_it_lists_regionais_for_select(): void
    {
        Regional::create(['nome' => 'Select Regional', 'cidade' => 'City', 'estado' => 'SP', 'created_by' => $this->adminUser->id]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/regionais/select');

        $response->assertOk()
            ->assertJsonPath('message', 'Regionais recuperadas com sucesso');
    }
}
