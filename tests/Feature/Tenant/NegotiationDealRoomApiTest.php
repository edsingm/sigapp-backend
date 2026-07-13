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
use App\Models\Tenant\Contrato;
use App\Models\Tenant\Negociacao;
use App\Models\Tenant\Terreno;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NegotiationDealRoomApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Negociacao $negotiation;

    private Contrato $contract;

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
            'name' => 'Deal Room Admin',
            'email' => 'deal-room-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);

        $terreno = Terreno::create(['nome' => 'Terreno deal room', 'created_by' => $this->admin->id]);
        $this->negotiation = Negociacao::create([
            'terreno_id' => $terreno->id,
            'status' => 'em_negociacao',
            'created_by' => $this->admin->id,
        ]);
        $this->contract = Contrato::create([
            'terreno_id' => $terreno->id,
            'negociacao_id' => $this->negotiation->id,
            'status' => 'minuta_contratual',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_version_offers_approve_and_track_contract_conditions(): void
    {
        $offer = $this->actingAs($this->admin)->postJson(
            "/api/v1/negociacoes/{$this->negotiation->id}/offers",
            [
                'offer_type' => 'proposal',
                'amount' => 1500000,
                'terms' => ['permuta' => 20],
                'status' => 'submitted',
            ],
        )->assertCreated()->assertJsonPath('data.version', 1)->json('data.id');

        $this->actingAs($this->admin)->postJson(
            "/api/v1/negociacoes/{$this->negotiation->id}/offers/{$offer}/accept",
        )->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->actingAs($this->admin)->postJson(
            "/api/v1/negociacoes/{$this->negotiation->id}/approvals",
            ['area' => 'juridico', 'decision' => 'approved', 'comment' => 'Sem ressalvas.'],
        )->assertCreated()->assertJsonPath('data.decision', 'approved');

        $this->actingAs($this->admin)->postJson(
            "/api/v1/contratos/{$this->contract->id}/conditions",
            ['title' => 'Apresentar certidão', 'status' => 'pending'],
        )->assertCreated()->assertJsonPath('data.status', 'pending');

        $conditionId = $this->actingAs($this->admin)->getJson(
            "/api/v1/contratos/{$this->contract->id}/conditions",
        )->assertOk()->json('data.0.id');

        $this->actingAs($this->admin)->patchJson(
            "/api/v1/contratos/{$this->contract->id}/conditions/{$conditionId}",
            ['status' => 'fulfilled'],
        )->assertOk()->assertJsonPath('data.status', 'fulfilled');
    }
}
