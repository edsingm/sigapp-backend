<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnforcePlanLimits;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Tenant\Produto;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProdutosControllerTest extends TestCase
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
            CheckFeature::class,
            EnforcePlanLimits::class,
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

    public function test_it_lists_produtos(): void
    {
        Produto::create(['name' => 'Produto A', 'description' => 'Desc A', 'price' => 10.0]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/produtos');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('data.0.name', 'Produto A');
    }

    public function test_it_shows_a_produto(): void
    {
        $produto = Produto::create(['name' => 'Produto B', 'description' => 'Desc B', 'price' => 20.0]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/produtos/{$produto->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Produto B');
    }

    public function test_it_returns_404_for_missing_produto(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/produtos/99999');

        $response->assertNotFound();
    }

    public function test_it_creates_a_produto(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/produtos', [
                'name' => 'Novo Produto',
                'description' => 'Nova Desc',
                'price' => 30.0,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Novo Produto');

        $this->assertDatabaseHas('produtos', ['name' => 'Novo Produto']);
    }

    public function test_it_updates_a_produto(): void
    {
        $produto = Produto::create(['name' => 'Old', 'description' => 'Old Desc', 'price' => 10.0]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/produtos/{$produto->id}", [
                'name' => 'Updated',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated');
    }

    public function test_it_records_and_lists_produto_history(): void
    {
        $produto = Produto::create([
            'name' => 'Produto auditado',
            'm2_cost' => 1000,
            'created_by' => $this->adminUser->id,
            'updated_by' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/produtos/{$produto->id}", [
                'name' => 'Produto auditado',
                'm2_cost' => 1200,
            ])
            ->assertOk();

        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/produtos/{$produto->id}", [
                'status' => 'inativo',
            ])
            ->assertOk();

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/produtos/{$produto->id}/historico");

        $response->assertOk()
            ->assertJsonPath('data.current.id', $produto->id)
            ->assertJsonPath('data.current.updated_by_user.name', 'Admin Test')
            ->assertJsonCount(2, 'data.entries')
            ->assertJsonPath('data.entries.0.changed_by_user.name', 'Admin Test')
            ->assertJsonPath('data.entries.1.before_values.m2_cost', '1000.00')
            ->assertJsonPath('data.entries.1.after_values.m2_cost', '1200.00');
    }

    public function test_it_deletes_a_produto(): void
    {
        $produto = Produto::create(['name' => 'To Delete', 'description' => 'Desc', 'price' => 10.0]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/produtos/{$produto->id}");

        $response->assertOk();
        $this->assertSoftDeleted('produtos', ['id' => $produto->id]);
    }

    public function test_it_restores_a_produto(): void
    {
        $produto = Produto::create(['name' => 'To Restore', 'description' => 'Desc', 'price' => 10.0]);
        $produto->delete();

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/produtos/{$produto->id}/restore");

        $response->assertOk();
        $this->assertDatabaseHas('produtos', ['id' => $produto->id, 'deleted_at' => null]);
    }
}
