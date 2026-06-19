<?php

namespace Tests\Feature\Signup;

use App\Http\Controllers\Api\V1\SignupController;
use App\Http\Requests\SignupRequest;
use App\Models\Central\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\Billing\StripeCheckoutService;
use App\Services\Billing\TenantBillingService;
use App\Services\Signup\TenantSignupService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class TrialEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makePlan(): Plan
    {
        return Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Plano Pro',
            'stripe_price_id' => 'price_pro',
            'price' => 19900,
            'sort_order' => 10,
            'is_active' => true,
            'trial_days' => 14,
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedData(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'empresa-'.uniqid(),
            'organization_name' => 'Minha Empresa',
            'admin_name' => 'Admin',
            'admin_email' => 'dono@example.com',
            'admin_password' => 'secret123',
            'plan_slug' => 'pro',
        ], $overrides);
    }

    private function request(): Request
    {
        return Request::create('/api/v1/signup', 'POST');
    }

    // -------------------------------------------------------------------------
    // Elegibilidade — TenantSignupService
    // -------------------------------------------------------------------------

    public function test_first_signup_is_trial_eligible_and_writes_ledger(): void
    {
        $plan = $this->makePlan();
        $service = app(TenantSignupService::class);

        $result = $service->createPendingTenant($this->validatedData(), $plan, $this->request());

        $this->assertTrue($result['trial_eligible']);
        $this->assertNotNull($result['tenant']->getAttribute('trial_ends_at'));
        $this->assertDatabaseHas('trial_ledger', ['email' => 'dono@example.com']);
        $this->assertSame(1, DB::table('trial_ledger')->where('email', 'dono@example.com')->count());
    }

    public function test_repeated_email_is_not_eligible_even_with_different_casing(): void
    {
        $plan = $this->makePlan();
        $service = app(TenantSignupService::class);

        // Primeiro cadastro consome a elegibilidade.
        $service->createPendingTenant($this->validatedData(['admin_email' => 'dono@example.com']), $plan, $this->request());

        // Segundo cadastro: mesmo email com casing/espaços diferentes.
        $result = $service->createPendingTenant(
            $this->validatedData(['admin_email' => '  Dono@Example.COM ']),
            $plan,
            $this->request(),
        );

        $this->assertFalse($result['trial_eligible']);
        $this->assertNull($result['tenant']->getAttribute('trial_ends_at'));
        // Continua existindo apenas uma linha normalizada no ledger.
        $this->assertSame(1, DB::table('trial_ledger')->where('email', 'dono@example.com')->count());
    }

    public function test_trial_ledger_email_has_unique_constraint(): void
    {
        DB::table('trial_ledger')->insert([
            'email' => 'a@b.com', 'tenant_slug' => 's1', 'granted_at' => now(), 'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('trial_ledger')->insert([
            'email' => 'a@b.com', 'tenant_slug' => 's2', 'granted_at' => now(), 'created_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Wiring — controller repassa trialEligible ao Checkout
    // -------------------------------------------------------------------------

    public function test_repeated_email_passes_trial_eligible_false_to_checkout(): void
    {
        $plan = $this->makePlan();

        // Email já presente no ledger (cadastro anterior) → inelegível.
        DB::table('trial_ledger')->insert([
            'email' => 'dono@example.com', 'tenant_slug' => 'outra-empresa', 'granted_at' => now(), 'created_at' => now(),
        ]);

        $checkout = Mockery::mock(StripeCheckoutService::class);
        $checkout->shouldReceive('createCustomer')->once()
            ->andReturn(\Stripe\Customer::constructFrom(['id' => 'cus_test']));
        $checkout->shouldReceive('createSubscriptionSession')
            ->once()
            ->withArgs(fn ($tenant, $p, $customerId, $trialEligible) => $trialEligible === false)
            ->andReturn(\Stripe\Checkout\Session::constructFrom([
                'id' => 'cs_test',
                'url' => 'https://checkout.stripe.test/cs_test',
            ]));

        $planRepo = Mockery::mock(PlanRepositoryInterface::class);
        $planRepo->shouldReceive('findActiveBySlug')->with('pro')->andReturn($plan);

        $controller = new SignupController(
            Mockery::mock(TenantBillingService::class),
            app(TenantSignupService::class),
            $checkout,
            $planRepo,
            Mockery::mock(TenantRepositoryInterface::class),
        );

        $requestMock = Mockery::mock(SignupRequest::class);
        $requestMock->shouldReceive('validated')->andReturn($this->validatedData(['admin_email' => 'dono@example.com']));
        $requestMock->shouldReceive('ip')->andReturn('127.0.0.1');
        $requestMock->shouldReceive('userAgent')->andReturn('PHPUnit');

        $response = $controller->store($requestMock);

        $this->assertSame(200, $response->getStatusCode());
    }
}
