<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Central\Tenant;
use App\Services\Billing\TenantBillingService;
use Mockery;
use PHPUnit\Framework\Attributes\After;
use Tests\TestCase;

class TenantBillingServiceTest extends TestCase
{
    #[After]
    public function tearDownMockery(): void
    {
        Mockery::close();
    }

    public function test_matches_signup_checkout_session_reads_nested_tenant_data(): void
    {
        $tenant = new Tenant();
        $tenant->data = [
            'signup_contract_acceptance' => [
                'stripe_checkout_session_id' => 'cs_test_123',
            ],
        ];

        $service = new TenantBillingService();

        self::assertTrue($service->matchesSignupCheckoutSession($tenant, 'cs_test_123'));
        self::assertFalse($service->matchesSignupCheckoutSession($tenant, 'cs_test_other'));
    }

    public function test_apply_stripe_subscription_status_activates_active_subscription(): void
    {
        $tenant = Mockery::mock(Tenant::class);
        $tenant->shouldReceive('activate')->once()->andReturnSelf();

        $service = new TenantBillingService();

        self::assertSame(Tenant::STATUS_ACTIVE, $service->applyStripeSubscriptionStatus($tenant, 'active'));
    }

    public function test_apply_stripe_subscription_status_suspends_unpaid_subscription(): void
    {
        $tenant = Mockery::mock(Tenant::class);
        $tenant->shouldReceive('suspend')->once()->andReturnSelf();

        $service = new TenantBillingService();

        self::assertSame(Tenant::STATUS_SUSPENDED, $service->applyStripeSubscriptionStatus($tenant, 'unpaid'));
    }

    public function test_apply_stripe_subscription_status_cancels_canceled_subscription(): void
    {
        $tenant = Mockery::mock(Tenant::class);
        $tenant->shouldReceive('cancel')->once()->andReturnSelf();

        $service = new TenantBillingService();

        self::assertSame(Tenant::STATUS_CANCELLED, $service->applyStripeSubscriptionStatus($tenant, 'canceled'));
    }

    public function test_apply_stripe_subscription_status_ignores_past_due_subscription(): void
    {
        $tenant = Mockery::mock(Tenant::class);
        $tenant->shouldNotReceive('activate');
        $tenant->shouldNotReceive('suspend');
        $tenant->shouldNotReceive('cancel');

        $service = new TenantBillingService();

        self::assertSame(TenantBillingService::STATUS_NOOP, $service->applyStripeSubscriptionStatus($tenant, 'past_due'));
    }
}
