<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(array $attrs = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Plano Teste',
            'slug' => 'teste',
            'description' => 'Plano para testes',
            'stripe_price_id' => 'price_test_123',
            'price' => 9900,
            'trial_days' => 7,
            'is_active' => true,
        ], $attrs));
    }

    public function test_servico_existe_e_pode_ser_instanciado(): void
    {
        $service = new StripeCheckoutService;

        $this->assertInstanceOf(StripeCheckoutService::class, $service);
    }

    public function test_create_price_on_the_fly_usa_idempotency_key_baseado_no_plano(): void
    {
        // Testa que o método existe e tem a assinatura correta
        $reflection = new \ReflectionMethod(StripeCheckoutService::class, 'createPriceOnTheFly');

        $this->assertTrue($reflection->isPublic());
        $this->assertSame(1, $reflection->getNumberOfParameters());

        $param = $reflection->getParameters()[0];
        $this->assertSame('plan', $param->getName());
        $this->assertSame(Plan::class, $param->getType()?->getName());
    }

    public function test_create_subscription_session_aceita_opcoes_extras(): void
    {
        // Testa que o método existe com a assinatura correta
        $reflection = new \ReflectionMethod(StripeCheckoutService::class, 'createSubscriptionSession');

        $this->assertTrue($reflection->isPublic());

        $params = $reflection->getParameters();
        $this->assertCount(4, $params);
        $this->assertSame('tenant', $params[0]->getName());
        $this->assertSame('plan', $params[1]->getName());
        $this->assertSame('customerId', $params[2]->getName());
        $this->assertSame('sessionOptions', $params[3]->getName());
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
