<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Common\RolesEnum;
use App\Http\Middleware\AddTenantContextToLogs;
use App\Http\Middleware\ApiRequestLogger;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\InitializeTenancyFlexible;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Services\Billing\TenantBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Garante enforcement HTTP de assinatura e reativação após regularização.
 */
class SubscriptionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Mantém CheckSubscriptionStatus; remove apenas identificação de host/tenancy.
        $this->withoutMiddleware([
            InitializeTenancyFlexible::class,
            AddTenantContextToLogs::class,
            ApiRequestLogger::class,
            EnsureTenantContext::class,
            EnsureTenantUser::class,
        ]);

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant', '--realpath' => false]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::query()->firstOrCreate(['name' => RolesEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->tenant = Tenant::query()->create([
            'name' => 'Tenant Billing Enforcement',
            'slug' => 'tenant-billing-enforcement',
            'status' => Tenant::STATUS_ACTIVE,
            'stripe_id' => 'cus_enforcement_test',
            'billing_profile_required' => false,
        ]);
        $this->tenant->domains()->create(['domain' => 'tenant-billing-enforcement']);

        $this->user = User::query()->create([
            'name' => 'Billing User',
            'email' => 'billing-user@test.com',
            'password' => Hash::make('password123'),
        ]);
        $this->user->assignRole(RolesEnum::ADMIN);

        tenancy()->tenant = $this->tenant;
        tenancy()->initialized = true;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        tenancy()->tenant = null;
        tenancy()->initialized = false;

        parent::tearDown();
    }

    public function test_suspended_tenant_is_blocked_on_business_routes(): void
    {
        $this->tenant->suspend();

        $this->actingAs($this->user)
            ->getJson('/api/v1/tenant')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'SUBSCRIPTION_INACTIVE')
            ->assertJsonPath('error.details.status', Tenant::STATUS_SUSPENDED);
    }

    public function test_suspended_tenant_can_access_dunning_payment_status(): void
    {
        $this->tenant->suspend();

        $billing = Mockery::mock(TenantBillingService::class);
        $billing->shouldReceive('getPaymentRetryStatus')
            ->once()
            ->andReturn([
                'status' => Tenant::STATUS_SUSPENDED,
                'has_open_invoice' => true,
                'invoice_url' => 'https://invoice.stripe.test/in_open',
            ]);
        $this->app->instance(TenantBillingService::class, $billing);

        // Rota de regularização fica fora de CheckSubscriptionStatus.
        $this->actingAs($this->user)
            ->getJson('/api/v1/tenant/billing/payment-status')
            ->assertOk()
            ->assertJsonPath('data.has_open_invoice', true);
    }

    public function test_suspended_tenant_billing_history_route_is_not_blocked_by_subscription_middleware(): void
    {
        $this->tenant->suspend();

        // Pode falhar por gate/policy ou Stripe, mas não por SUBSCRIPTION_INACTIVE.
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/tenant/billing/history');

        $this->assertNotSame(
            'SUBSCRIPTION_INACTIVE',
            $response->json('error.code'),
            (string) $response->getContent(),
        );
    }

    public function test_active_tenant_can_access_business_routes(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/tenant')
            ->assertOk();
    }

    public function test_apply_stripe_active_status_reactivates_suspended_tenant(): void
    {
        $this->tenant->suspend();
        $this->assertFalse($this->tenant->fresh()?->isActive() ?? true);

        $applied = app(TenantBillingService::class)
            ->applyStripeSubscriptionStatus($this->tenant, 'active');

        $this->assertSame(Tenant::STATUS_ACTIVE, $applied);
        $this->assertTrue($this->tenant->fresh()?->isActive() ?? false);
    }

    public function test_under_review_tenant_is_blocked_on_business_routes(): void
    {
        $this->tenant->placeUnderReview();

        $this->actingAs($this->user)
            ->getJson('/api/v1/tenant')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'SUBSCRIPTION_INACTIVE')
            ->assertJsonPath('error.details.status', Tenant::STATUS_UNDER_REVIEW);
    }
}
