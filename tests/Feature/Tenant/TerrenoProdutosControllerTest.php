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
use App\Models\Tenant\Terreno;
use App\Models\Tenant\TerrenoProduto;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TerrenoProdutosControllerTest extends TestCase
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

    public function test_it_lists_terreno_produtos(): void
    {
        $terreno = Terreno::create(['nome' => 'Terreno A', 'endereco' => 'Rua A']);
        $produto = Produto::create(['name' => 'Produto A']);
        TerrenoProduto::create(['terreno_id' => $terreno->id, 'produto_id' => $produto->id]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/terreno-produtos');

        $response->assertOk()
            ->assertJsonPath('message', 'Associações terreno-produto recuperadas com sucesso');
    }

    public function test_it_shows_a_terreno_produto(): void
    {
        $terreno = Terreno::create(['nome' => 'Terreno B', 'endereco' => 'Rua B']);
        $produto = Produto::create(['name' => 'Produto B']);
        $tp = TerrenoProduto::create(['terreno_id' => $terreno->id, 'produto_id' => $produto->id]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/terreno-produtos/{$tp->id}");

        $response->assertOk();
    }

    public function test_it_returns_404_for_missing_terreno_produto(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/terreno-produtos/99999');

        $response->assertNotFound();
    }

    public function test_it_creates_a_terreno_produto(): void
    {
        $terreno = Terreno::create(['nome' => 'Terreno C', 'endereco' => 'Rua C']);
        $produto = Produto::create(['name' => 'Produto C']);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/terreno-produtos', [
                'terreno_id' => $terreno->id,
                'produto_id' => $produto->id,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('terreno_produtos', [
            'terreno_id' => $terreno->id,
            'produto_id' => $produto->id,
        ]);
    }

    public function test_it_updates_a_terreno_produto(): void
    {
        $terreno = Terreno::create(['nome' => 'Terreno D', 'endereco' => 'Rua D']);
        $produto = Produto::create(['name' => 'Produto D']);
        $tp = TerrenoProduto::create(['terreno_id' => $terreno->id, 'produto_id' => $produto->id]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/v1/terreno-produtos/{$tp->id}", [
                'terreno_id' => $terreno->id,
                'produto_id' => $produto->id,
            ]);

        $response->assertOk();
    }

    public function test_it_deletes_a_terreno_produto(): void
    {
        $terreno = Terreno::create(['nome' => 'Terreno E', 'endereco' => 'Rua E']);
        $produto = Produto::create(['name' => 'Produto E']);
        $tp = TerrenoProduto::create(['terreno_id' => $terreno->id, 'produto_id' => $produto->id]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/terreno-produtos/{$tp->id}");

        $response->assertOk();
        $this->assertSoftDeleted('terreno_produtos', ['id' => $tp->id]);
    }
}