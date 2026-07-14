<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Central\Plan;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Stripe\Price;
use Stripe\Product;
use Stripe\Service\PriceService;
use Stripe\Service\ProductService;
use Stripe\StripeClient;
use Tests\TestCase;

class TestableStripeCheckoutService extends StripeCheckoutService
{
    public StripeClient $fakeStripe;

    protected function stripe(): StripeClient
    {
        return $this->fakeStripe;
    }
}

class CheckoutTestStripeClient extends StripeClient
{
    public function __construct(
        public ProductService $products,
        public PriceService $prices,
    ) {}
}

class StripeCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_servico_existe_e_pode_ser_instanciado(): void
    {
        $service = new StripeCheckoutService;

        $this->assertInstanceOf(StripeCheckoutService::class, $service);
    }

    public function test_create_price_on_the_fly_usa_idempotency_key_baseado_no_plano(): void
    {
        $plan = Plan::create([
            'name' => 'SIG - Broker',
            'slug' => 'broker',
            'price' => 97.00,
            'trial_days' => 7,
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
        ]);

        /** @var ProductService&MockInterface $products */
        $products = Mockery::mock(ProductService::class);
        $products->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $params, array $options): bool => $params['name'] === 'SIG - Broker'
                && $options['idempotency_key'] === 'product-plan-'.$plan->id.'-broker')
            ->andReturn(Product::constructFrom(['id' => 'prod_test']));

        /** @var PriceService&MockInterface $prices */
        $prices = Mockery::mock(PriceService::class);
        $prices->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $params, array $options): bool => $params['product'] === 'prod_test'
                && $params['unit_amount'] === 9700
                && $params['currency'] === 'brl'
                && $params['recurring'] === ['interval' => 'month']
                && $options['idempotency_key'] === 'price-plan-'.$plan->id.'-broker-9700')
            ->andReturn(Price::constructFrom(['id' => 'price_test']));

        $service = new TestableStripeCheckoutService;
        $service->fakeStripe = new CheckoutTestStripeClient($products, $prices);

        $priceId = $service->createPriceOnTheFly($plan);

        $this->assertSame('price_test', $priceId);
        $this->assertSame('price_test', $plan->fresh()->stripe_price_id);
    }

    public function test_create_subscription_session_aceita_opcoes_extras(): void
    {
        // Testa que o método existe com a assinatura correta
        $reflection = new \ReflectionMethod(StripeCheckoutService::class, 'createSubscriptionSession');

        $this->assertTrue($reflection->isPublic());

        $params = $reflection->getParameters();
        $this->assertCount(5, $params);
        $this->assertSame('tenant', $params[0]->getName());
        $this->assertSame('plan', $params[1]->getName());
        $this->assertSame('customerId', $params[2]->getName());
        $this->assertSame('trialEligible', $params[3]->getName());
        $this->assertSame('sessionOptions', $params[4]->getName());
    }

    public function test_create_customer_aceita_tenant_e_validated(): void
    {
        $reflection = new \ReflectionMethod(StripeCheckoutService::class, 'createCustomer');

        $this->assertTrue($reflection->isPublic());

        $params = $reflection->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('tenant', $params[0]->getName());
        $this->assertSame('validated', $params[1]->getName());
    }
}
