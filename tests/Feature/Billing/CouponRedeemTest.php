<?php

namespace Tests\Feature\Billing;

use App\Models\Central\Coupon;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Billing\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Stripe\StripeClient;
use Tests\TestCase;

/**
 * Injeta um StripeClient fake no CouponService, evitando chamadas reais ao Stripe.
 */
class TestableCouponService extends CouponService
{
    public StripeClient $fakeStripe;

    protected function stripe(): StripeClient
    {
        return $this->fakeStripe;
    }
}

class CouponRedeemTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeTenantWithSubscription(int $planId, string $subscriptionId): Tenant
    {
        $id = Str::uuid()->toString();

        DB::table('tenants')->insert([
            'id' => $id,
            'name' => 'Test Tenant',
            'slug' => 'test-'.uniqid(),
            'status' => Tenant::STATUS_ACTIVE,
            'stripe_id' => 'cus_test_'.uniqid(),
            'stripe_subscription_id' => $subscriptionId,
            'plan_id' => $planId,
            'admin_email' => 'admin@test.com',
            'admin_name' => 'Admin',
            'database_created' => false,
            'trial_extended' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Tenant::find($id);
    }

    public function test_redeem_uses_discounts_param_and_does_not_increment_locally(): void
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro', 'stripe_price_id' => 'price_pro',
            'price' => 10000, 'sort_order' => 10, 'is_active' => true, 'trial_days' => 0,
        ]);

        $coupon = Coupon::create([
            'stripe_coupon_id' => 'disc_test',
            'code' => 'PROMO',
            'name' => 'Promo',
            'type' => 'percent',
            'percent_off' => 10,
            'is_active' => true,
        ]);

        $tenant = $this->makeTenantWithSubscription($plan->id, 'sub_test');

        $subscriptions = Mockery::mock(\Stripe\Service\SubscriptionService::class);
        $subscriptions->shouldReceive('update')
            ->once()
            ->withArgs(fn ($id, $params) => $id === 'sub_test'
                && ($params['discounts'] ?? null) === [['coupon' => 'disc_test']]
                && ! array_key_exists('coupon', $params))
            ->andReturnNull();

        $client = Mockery::mock(StripeClient::class);
        $client->subscriptions = $subscriptions;

        $service = new TestableCouponService;
        $service->fakeStripe = $client;

        $result = $service->redeem($tenant, 'promo');

        $this->assertTrue($result['success']);
        // Contagem é responsabilidade do webhook customer.discount.created (fonte única).
        $this->assertSame(0, $coupon->fresh()->times_redeemed);
    }
}
