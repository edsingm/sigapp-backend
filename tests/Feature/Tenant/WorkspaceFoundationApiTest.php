<?php

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckFeature;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Http\Middleware\PermissionGate;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkspaceFoundationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Terreno $terreno;

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
            CheckFeature::class,
            PermissionGate::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Workspace Foundation Admin',
            'email' => 'workspace-foundation-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);
        $this->terreno = Terreno::create(['nome' => 'Alpha Norte', 'created_by' => $this->admin->id]);
    }

    public function test_admin_can_search_save_a_view_and_update_preferences(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/search?query=Alpha&types[]=terreno&limit=8')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Alpha Norte')
            ->assertJsonPath('data.0.entity.type', 'terreno');

        $viewId = $this->actingAs($this->admin)->postJson('/api/v1/saved-views', [
            'name' => 'Terrenos prioritários',
            'resource' => 'terrenos',
            'scope' => 'private',
            'filters' => ['status' => 'em_analise'],
            'is_default' => true,
        ])->assertCreated()->assertJsonPath('data.is_default', true)->json('data.id');

        $this->actingAs($this->admin)->getJson('/api/v1/saved-views?resource=terrenos')
            ->assertOk()->assertJsonPath('data.0.id', $viewId);

        $this->actingAs($this->admin)->patchJson('/api/v1/me/preferences', [
            'theme' => 'dark',
            'density' => 'compact',
            'favorites' => [['id' => $this->terreno->id, 'type' => 'terreno']],
        ])->assertOk()->assertJsonPath('data.theme', 'dark');

        $this->actingAs($this->admin)->getJson('/api/v1/me/preferences')
            ->assertOk()->assertJsonPath('data.favorites.0.type', 'terreno');

        $this->assertDatabaseHas('saved_views', ['id' => $viewId, 'owner_id' => $this->admin->id]);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'density' => 'compact']);
    }
}
