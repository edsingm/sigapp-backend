<?php

namespace Tests\Unit\Services\Billing;

use App\Http\Resources\TenantBillingAddonResource;
use App\Models\Central\BillingAddon;
use App\Services\Billing\BillingAddonPricingService;
use App\Services\Billing\TenantBillingService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BillingAddonPricingServiceTest extends TestCase
{
    public function test_it_reads_and_formats_an_active_monthly_stripe_price(): void
    {
        Cache::forget('billing-addon-price:price_storage');

        /** @var TenantBillingService&MockInterface $billingService */
        $billingService = Mockery::mock(TenantBillingService::class);
        $billingService->shouldReceive('retrievePrice')
            ->once()
            ->with('price_storage')
            ->andReturn((object) [
                'active' => true,
                'unit_amount' => 1299,
                'currency' => 'brl',
                'recurring' => (object) ['interval' => 'month'],
            ]);

        /** @var BillingAddon $addon */
        $addon = BillingAddon::factory()->make([
            'stripe_price_id' => 'price_storage',
            'currency' => 'brl',
            'billing_interval' => 'month',
        ]);

        $details = (new BillingAddonPricingService($billingService))->details($addon);

        $this->assertSame(1299, $details['unit_amount']);
        $this->assertSame('brl', $details['currency']);
        $this->assertSame('month', $details['interval']);
        $this->assertSame('R$ 12,99/mês', $details['formatted_price']);
        $this->assertTrue($details['is_purchasable']);
    }

    public function test_it_does_not_mark_non_monthly_prices_as_purchasable(): void
    {
        Cache::forget('billing-addon-price:price_annual');

        /** @var TenantBillingService&MockInterface $billingService */
        $billingService = Mockery::mock(TenantBillingService::class);
        $billingService->shouldReceive('retrievePrice')
            ->once()
            ->with('price_annual')
            ->andReturn((object) [
                'active' => true,
                'unit_amount' => 1299,
                'currency' => 'brl',
                'recurring' => (object) ['interval' => 'year'],
            ]);

        /** @var BillingAddon $addon */
        $addon = BillingAddon::factory()->make(['stripe_price_id' => 'price_annual']);
        $details = (new BillingAddonPricingService($billingService))->details($addon);

        $this->assertFalse($details['is_purchasable']);
        $this->assertNull($details['unit_amount']);
        $this->assertNull($details['formatted_price']);
    }

    public function test_it_accepts_and_formats_an_active_one_time_price(): void
    {
        Cache::setDefaultDriver('array');
        Cache::forget('billing-addon-price:price_ai_credit');

        /** @var TenantBillingService&MockInterface $billingService */
        $billingService = Mockery::mock(TenantBillingService::class);
        $billingService->shouldReceive('retrievePrice')
            ->once()
            ->with('price_ai_credit')
            ->andReturn((object) [
                'active' => true,
                'type' => 'one_time',
                'unit_amount' => 3500,
                'currency' => 'brl',
                'recurring' => null,
            ]);

        /** @var BillingAddon $addon */
        $addon = BillingAddon::factory()->make([
            'stripe_price_id' => 'price_ai_credit',
            'billing_interval' => 'one_time',
        ]);
        $details = (new BillingAddonPricingService($billingService))->details($addon);

        $this->assertSame(3500, $details['unit_amount']);
        $this->assertSame('one_time', $details['interval']);
        $this->assertSame('one_time', $details['price_type']);
        $this->assertSame('R$ 35,00', $details['formatted_price']);
        $this->assertTrue($details['is_purchasable']);
    }

    public function test_tenant_resource_exposes_price_contract_without_stripe_identifiers(): void
    {
        /** @var BillingAddon $addon */
        $addon = BillingAddon::factory()->make([
            'stripe_price_id' => 'price_private',
            'currency' => 'brl',
            'billing_interval' => 'month',
        ]);
        $addon->setAttribute('price_details', [
            'unit_amount' => 1299,
            'currency' => 'brl',
            'interval' => 'month',
            'formatted_price' => 'R$ 12,99/mês',
            'is_purchasable' => true,
        ]);

        $data = (new TenantBillingAddonResource($addon))->toArray(request());

        $this->assertSame(1299, data_get($data, 'price.unit_amount'));
        $this->assertSame('R$ 12,99/mês', $data['formatted_price']);
        $this->assertTrue($data['is_purchasable']);
        $this->assertArrayNotHasKey('stripe_price_id', $data);
    }
}
