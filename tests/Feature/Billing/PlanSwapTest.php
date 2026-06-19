<?php

namespace Tests\Feature\Billing;

use App\Http\Controllers\Api\V1\Tenant\PlanSwapController;
use App\Http\Requests\Tenant\PlanSwapRequest;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Payment;
use Laravel\Cashier\Subscription;
use Mockery;
use Mockery\MockInterface;
use Stancl\Tenancy\Tenancy;
use Tests\TestCase;

/**
 * Stub de Tenant que sobrescreve subscription() para evitar chamadas ao Stripe.
 * Mantém toda a lógica Eloquent (update, relations, getAttribute) intacta.
 */
class TenantWithFakeSubscription extends Tenant
{
    protected $table = 'tenants';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public ?Subscription $fakeSubscription = null;

    /** @param string $type */
    public function subscription($type = 'default'): ?Subscription
    {
        return $this->fakeSubscription;
    }
}

class PlanSwapTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePlan(string $name, int $sortOrder, string $priceId): Plan
    {
        return Plan::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'stripe_price_id' => $priceId,
            'price' => 10000,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'trial_days' => 0,
        ]);
    }

    /**
     * Cria o tenant diretamente no DB (evita events do Stancl que criam bancos).
     */
    private function insertTenant(int $planId, ?int $scheduledPlanId = null): string
    {
        $id = Str::uuid()->toString();

        DB::table('tenants')->insert([
            'id' => $id,
            'name' => 'Test Tenant',
            'slug' => 'test-'.uniqid(),
            'status' => Tenant::STATUS_ACTIVE,
            'stripe_id' => 'cus_test_'.uniqid(),
            'plan_id' => $planId,
            'scheduled_plan_id' => $scheduledPlanId,
            'admin_email' => 'admin@test.com',
            'admin_name' => 'Admin',
            'database_created' => false,
            'trial_extended' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function loadTenant(string $tenantId): TenantWithFakeSubscription
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        if ($tenant === null) {
            throw new \RuntimeException('Tenant not found in test setup.');
        }

        $model = new TenantWithFakeSubscription;
        $model->exists = true;
        $model->setRawAttributes((array) $tenant, true);

        return $model;
    }

    /**
     * Retorna um mock de Subscription com active()=true e stripe_price definido.
     *
     * @return Subscription&MockInterface
     */
    private function makeSubscriptionMock(string $currentStripePriceId): Subscription
    {
        /** @var Subscription&MockInterface $mock */
        $mock = Mockery::mock(Subscription::class)->makePartial();
        $mock->shouldReceive('active')->andReturn(true);
        $mock->shouldReceive('getAttribute')
            ->with('stripe_price')
            ->andReturn($currentStripePriceId);

        return $mock;
    }

    /**
     * Cria um mock de PlanSwapRequest que retorna o slug sem autenticação/validação real.
     *
     * @return PlanSwapRequest&MockInterface
     */
    private function makeSwapRequest(string $planSlug): PlanSwapRequest
    {
        /** @var PlanSwapRequest&MockInterface $mock */
        $mock = Mockery::mock(PlanSwapRequest::class);
        $mock->shouldReceive('validated')->with('plan_slug')->andReturn($planSlug);

        return $mock;
    }

    /**
     * @return Payment&MockInterface
     */
    private function makePaymentMock(): Payment
    {
        /** @var Payment&MockInterface $mock */
        $mock = Mockery::mock(Payment::class);

        return $mock;
    }

    // -------------------------------------------------------------------------
    // Downgrade diferido
    // -------------------------------------------------------------------------

    public function test_downgrade_sets_scheduled_plan_id_and_preserves_current_plan_id(): void
    {
        $planHigh = $this->makePlan('Pro', 10, 'price_pro');
        $planLow = $this->makePlan('Basic', 1, 'price_basic');

        $tenantId = $this->insertTenant($planHigh->id);
        $tenant = $this->loadTenant($tenantId);

        $subMock = $this->makeSubscriptionMock('price_pro');
        $subMock->shouldReceive('swap')->once()->with('price_basic')->andReturnSelf();

        $tenant->fakeSubscription = $subMock;
        app(Tenancy::class)->tenant = $tenant;

        $response = app(PlanSwapController::class)->swap($this->makeSwapRequest($planLow->slug));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'plan_id' => $planHigh->id,        // plano atual não muda até a renovação
            'scheduled_plan_id' => $planLow->id, // downgrade agendado
        ]);
    }

    public function test_downgrade_response_marks_as_not_upgrade(): void
    {
        $planHigh = $this->makePlan('Pro', 10, 'price_pro');
        $planLow = $this->makePlan('Basic', 1, 'price_basic');

        $tenantId = $this->insertTenant($planHigh->id);
        $tenant = $this->loadTenant($tenantId);

        $subMock = $this->makeSubscriptionMock('price_pro');
        $subMock->shouldReceive('swap')->once()->andReturnSelf();

        $tenant->fakeSubscription = $subMock;
        app(Tenancy::class)->tenant = $tenant;

        $response = app(PlanSwapController::class)->swap($this->makeSwapRequest($planLow->slug));
        $json = json_decode($response->getContent(), true);

        $this->assertFalse($json['data']['is_upgrade']);
    }

    // -------------------------------------------------------------------------
    // Upgrade imediato
    // -------------------------------------------------------------------------

    public function test_upgrade_sets_plan_id_immediately_and_clears_scheduled_plan_id(): void
    {
        $planLow = $this->makePlan('Basic', 1, 'price_basic');
        $planHigh = $this->makePlan('Pro', 10, 'price_pro');

        // Tenant tinha um downgrade pendente
        $tenantId = $this->insertTenant($planLow->id, $planHigh->id);
        $tenant = $this->loadTenant($tenantId);

        $subMock = $this->makeSubscriptionMock('price_basic');
        $subMock->shouldReceive('pendingIfPaymentFails')->once()->andReturnSelf();
        $subMock->shouldReceive('swapAndInvoice')->once()->with('price_pro')->andReturnSelf();

        $tenant->fakeSubscription = $subMock;
        app(Tenancy::class)->tenant = $tenant;

        $response = app(PlanSwapController::class)->swap($this->makeSwapRequest($planHigh->slug));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'plan_id' => $planHigh->id, // atualizado imediatamente
            'scheduled_plan_id' => null, // downgrade pendente cancelado
        ]);
    }

    public function test_upgrade_response_marks_as_upgrade(): void
    {
        $planLow = $this->makePlan('Basic', 1, 'price_basic');
        $planHigh = $this->makePlan('Pro', 10, 'price_pro');

        $tenantId = $this->insertTenant($planLow->id);
        $tenant = $this->loadTenant($tenantId);

        $subMock = $this->makeSubscriptionMock('price_basic');
        $subMock->shouldReceive('pendingIfPaymentFails')->once()->andReturnSelf();
        $subMock->shouldReceive('swapAndInvoice')->once()->andReturnSelf();

        $tenant->fakeSubscription = $subMock;
        app(Tenancy::class)->tenant = $tenant;

        $response = app(PlanSwapController::class)->swap($this->makeSwapRequest($planHigh->slug));
        $json = json_decode($response->getContent(), true);

        $this->assertTrue($json['data']['is_upgrade']);
    }

    public function test_upgrade_with_incomplete_payment_does_not_grant_plan(): void
    {
        $planLow = $this->makePlan('Basic', 1, 'price_basic');
        $planHigh = $this->makePlan('Pro', 10, 'price_pro');

        $tenantId = $this->insertTenant($planLow->id);
        $tenant = $this->loadTenant($tenantId);

        // Upgrade cujo pagamento exige ação (SCA) ou falha: swapAndInvoice lança
        // IncompletePayment. Com pendingIfPaymentFails() a troca fica em pending_update
        // no Stripe e o plano NÃO deve ser concedido localmente.
        $subMock = $this->makeSubscriptionMock('price_basic');
        $subMock->shouldReceive('pendingIfPaymentFails')->once()->andReturnSelf();
        $subMock->shouldReceive('swapAndInvoice')->once()->with('price_pro')
            ->andThrow(new IncompletePayment($this->makePaymentMock()));

        $tenant->fakeSubscription = $subMock;
        app(Tenancy::class)->tenant = $tenant;

        $response = app(PlanSwapController::class)->swap($this->makeSwapRequest($planHigh->slug));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'plan_id' => $planLow->id, // plano superior NÃO concedido sem pagamento
            'scheduled_plan_id' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    public function test_swap_returns_not_found_for_unknown_plan(): void
    {
        $planHigh = $this->makePlan('Pro', 10, 'price_pro');
        $tenantId = $this->insertTenant($planHigh->id);
        $tenant = $this->loadTenant($tenantId);

        $subMock = $this->makeSubscriptionMock('price_pro');
        $tenant->fakeSubscription = $subMock;
        app(Tenancy::class)->tenant = $tenant;

        $response = app(PlanSwapController::class)->swap($this->makeSwapRequest('non-existent-plan'));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_swap_returns_conflict_when_already_on_same_plan(): void
    {
        $plan = $this->makePlan('Pro', 10, 'price_pro');
        $tenantId = $this->insertTenant($plan->id);
        $tenant = $this->loadTenant($tenantId);

        // Subscription já está no mesmo preço Stripe que o plano solicitado
        $subMock = $this->makeSubscriptionMock('price_pro');
        $tenant->fakeSubscription = $subMock;
        app(Tenancy::class)->tenant = $tenant;

        $response = app(PlanSwapController::class)->swap($this->makeSwapRequest($plan->slug));

        $this->assertSame(409, $response->getStatusCode());
    }
}
