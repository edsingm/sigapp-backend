<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Models\Tenant\Produto;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
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
            \App\Http\Middleware\CheckFeature::class,
            \App\Http\Middleware\EnforcePlanLimits::class,
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
            ->assertJsonPath('message', 'Produtos recuperados com sucesso');
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