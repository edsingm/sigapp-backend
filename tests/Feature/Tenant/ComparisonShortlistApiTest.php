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

class ComparisonShortlistApiTest extends TestCase
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
            CheckFeature::class,
            PermissionGate::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Comparison Admin',
            'email' => 'comparison-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);
    }

    public function test_admin_can_create_shortlist_add_item_and_compare_terrains(): void
    {
        $first = Terreno::create(['nome' => 'Terreno A', 'created_by' => $this->admin->id]);
        $second = Terreno::create(['nome' => 'Terreno B', 'created_by' => $this->admin->id]);

        $create = $this->actingAs($this->admin)->postJson('/api/v1/shortlists', [
            'name' => 'Oportunidades prioritárias',
            'scope' => 'private',
        ]);

        $create->assertCreated()->assertJsonPath('data.name', 'Oportunidades prioritárias');
        $shortlistId = $create->json('data.id');

        $this->actingAs($this->admin)
            ->postJson("/api/v1/shortlists/{$shortlistId}/items", ['terreno_id' => $first->id])
            ->assertCreated()
            ->assertJsonPath('data.terreno_id', $first->id);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/shortlists')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.terreno.id', $first->id);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/terrenos/compare', [
                'terreno_ids' => [$first->id, $second->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.recommendation', null);
    }
}
