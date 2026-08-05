<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Enums\Common\RolesEnum;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\CheckSubscriptionStatus;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Services\Auth\CentralLoginBrokerService;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantBillingProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $commonUser;

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
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);
        Role::query()->firstOrCreate(['name' => RolesEnum::USER->value, 'guard_name' => 'web']);

        $this->tenant = Tenant::query()->create([
            'name' => 'Tenant Fiscal',
            'slug' => 'tenant-fiscal',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
        $this->tenant->forceFill(['billing_profile_required' => true])->save();
        $this->tenant->domains()->create(['domain' => 'tenant-fiscal']);

        $this->admin = User::query()->create([
            'name' => 'Fiscal Admin',
            'email' => 'fiscal-admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->admin->assignRole(RolesEnum::ADMIN);

        $this->commonUser = User::query()->create([
            'name' => 'Common User',
            'email' => 'common-user@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->commonUser->assignRole(RolesEnum::USER);

        tenancy()->tenant = $this->tenant;
        tenancy()->initialized = true;
    }

    protected function tearDown(): void
    {
        tenancy()->tenant = null;
        tenancy()->initialized = false;

        parent::tearDown();
    }

    public function test_admin_completes_pj_profile_and_pii_is_encrypted_at_rest(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson('/api/v1/tenant/billing-profile', $this->validPjPayload())
            ->assertOk()
            ->assertJsonPath('data.type', 'pj')
            ->assertJsonPath('data.tax_id', '11222333000181')
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.required_action', null);

        self::assertNotNull($response->json('data.completed_at'));

        $tenant = $this->tenant->fresh();
        self::assertInstanceOf(Tenant::class, $tenant);
        self::assertSame('11222333000181', $tenant->billing_tax_id);
        self::assertArrayNotHasKey('billing_tax_id', $tenant->toArray());
        self::assertNotSame(
            '11222333000181',
            DB::table('tenants')->where('id', $tenant->id)->value('billing_tax_id'),
        );
    }

    public function test_profile_validates_person_type_and_restricts_non_admin(): void
    {
        $invalid = $this->validPjPayload();
        $invalid['tax_id'] = '529.982.247-25';

        $this->actingAs($this->admin)
            ->putJson('/api/v1/tenant/billing-profile', $invalid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tax_id']);

        $this->actingAs($this->commonUser)
            ->getJson('/api/v1/tenant/billing-profile')
            ->assertForbidden();
    }

    public function test_admin_can_save_an_alphanumeric_cnpj(): void
    {
        $payload = $this->validPjPayload();
        $payload['tax_id'] = '12.ABC.345/01de-35';

        $this->actingAs($this->admin)
            ->putJson('/api/v1/tenant/billing-profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.tax_id', '12ABC34501DE35')
            ->assertJsonPath('data.completed', true);
    }

    public function test_admin_completes_pf_profile_without_pj_only_fields(): void
    {
        $payload = $this->validPjPayload();
        $payload['type'] = 'pf';
        $payload['tax_id'] = '529.982.247-25';
        $payload['legal_name'] = 'Maria da Silva';
        unset($payload['trade_name'], $payload['municipal_registration'], $payload['tax_regime']);

        $this->actingAs($this->admin)
            ->putJson('/api/v1/tenant/billing-profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.type', 'pf')
            ->assertJsonPath('data.tax_id', '52998224725')
            ->assertJsonPath('data.trade_name', null)
            ->assertJsonPath('data.completed', true);
    }

    public function test_business_routes_are_blocked_until_profile_is_completed(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/me/preferences')
            ->assertStatus(428)
            ->assertJsonPath('error.code', 'TENANT_BILLING_PROFILE_INCOMPLETE')
            ->assertJsonPath('error.details.required_action', 'complete_tenant_billing_profile')
            ->assertJsonPath('error.details.can_complete', true);

        $this->actingAs($this->admin)
            ->putJson('/api/v1/tenant/billing-profile', $this->validPjPayload())
            ->assertOk();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/me/preferences')
            ->assertOk();
    }

    public function test_direct_login_exposes_required_action_without_pii(): void
    {
        $response = $this->withServerVariables(['HTTP_HOST' => 'tenant-fiscal.localhost'])
            ->postJson('http://tenant-fiscal.localhost/api/v1/auth/login', [
                'email' => $this->admin->email,
                'password' => 'password123',
            ])->assertOk()
            ->assertJsonPath('data.tenant.billing_profile.status', 'incomplete')
            ->assertJsonPath('data.tenant.billing_profile.required_action', 'complete_tenant_billing_profile')
            ->assertJsonPath('data.tenant.billing_profile.can_complete', true);

        self::assertArrayNotHasKey('tax_id', $response->json('data.tenant.billing_profile'));
    }

    public function test_exchange_ticket_exposes_the_same_required_action(): void
    {
        $this->mock(CentralLoginBrokerService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('redeemTransferTicket')->once()->andReturn([
                'user' => $this->admin,
                'token' => 'tenant-token',
                'abilities' => ['tenant-api'],
                'expires_at' => now()->addDay()->toIso8601String(),
            ]);
        });

        $this->withServerVariables(['HTTP_HOST' => 'tenant-fiscal.localhost'])
            ->postJson('http://tenant-fiscal.localhost/api/v1/auth/exchange-ticket', [
                'ticket' => str_repeat('a', 32),
            ])->assertOk()
            ->assertJsonPath('data.tenant.billing_profile.status', 'incomplete')
            ->assertJsonPath('data.tenant.billing_profile.required_action', 'complete_tenant_billing_profile')
            ->assertJsonPath('data.tenant.billing_profile.can_complete', true);
    }

    public function test_legacy_exemption_does_not_fabricate_completion_or_block_access(): void
    {
        $this->tenant->forceFill([
            'billing_profile_required' => false,
            'billing_profile_completed_at' => null,
        ])->save();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/me/preferences')
            ->assertOk();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/tenant/billing-profile')
            ->assertOk()
            ->assertJsonPath('data.status', 'exempt')
            ->assertJsonPath('data.completed', false);
    }

    public function test_stripe_sync_failure_does_not_discard_the_local_profile(): void
    {
        $this->tenant->forceFill(['stripe_id' => 'cus_test'])->save();

        $this->mock(StripeCheckoutService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('updateCustomerBillingProfile')
                ->once()
                ->andThrow(new RuntimeException('Stripe indisponível'));
        });

        $this->actingAs($this->admin)
            ->putJson('/api/v1/tenant/billing-profile', $this->validPjPayload())
            ->assertOk()
            ->assertJsonPath('data.completed', true);

        $tenant = $this->tenant->fresh();
        self::assertInstanceOf(Tenant::class, $tenant);
        self::assertSame('Empresa Exemplo Ltda.', $tenant->billing_legal_name);
        self::assertNotNull($tenant->billing_profile_completed_at);
    }

    /** @return array<string, mixed> */
    private function validPjPayload(): array
    {
        return [
            'type' => 'pj',
            'tax_id' => '11.222.333/0001-81',
            'legal_name' => 'Empresa Exemplo Ltda.',
            'trade_name' => 'Empresa Exemplo',
            'email' => 'financeiro@exemplo.com.br',
            'phone' => '+55 11 99999-9999',
            'address' => [
                'postal_code' => '01310-100',
                'street' => 'Avenida Paulista',
                'number' => '1000',
                'complement' => 'Conjunto 101',
                'neighborhood' => 'Bela Vista',
                'city' => 'São Paulo',
                'state' => 'SP',
                'country' => 'BR',
            ],
            'municipal_registration' => null,
            'tax_regime' => 'simples_nacional',
        ];
    }
}
