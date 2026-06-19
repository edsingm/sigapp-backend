<?php

namespace Tests\Feature\Billing;

use App\Enums\TenantStatus;
use App\Http\Controllers\Api\V1\Tenant\DunningController;
use App\Models\Central\Tenant;
use App\Services\Billing\TenantBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Stancl\Tenancy\Tenancy;
use Stripe\Collection;
use Stripe\Invoice;
use Stripe\Service\InvoiceService;
use Stripe\StripeClient;
use Tests\TestCase;

/**
 * Subclasse que injeta um StripeClient fake, evitando chamadas reais ao Stripe.
 */
class TestableBillingService extends TenantBillingService
{
    public StripeClient $fakeStripe;

    protected function stripe(): StripeClient
    {
        return $this->fakeStripe;
    }
}

class PaymentRetryStripeClient extends StripeClient
{
    public function __construct(public InvoiceService $invoices)
    {
    }
}

class PaymentRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @return InvoiceService&MockInterface
     */
    private function fakeInvoiceService(): InvoiceService
    {
        /** @var InvoiceService&MockInterface $mock */
        $mock = Mockery::mock(InvoiceService::class);

        return $mock;
    }

    private function fakeStripeReturningInvoices(array $invoices): StripeClient
    {
        $invoiceService = $this->fakeInvoiceService();
        $invoiceService->shouldReceive('all')
            ->andReturn(Collection::constructFrom(['data' => $invoices]));

        return new PaymentRetryStripeClient($invoiceService);
    }

    private function tenantWithStripeId(string $stripeId = 'cus_test'): Tenant
    {
        $tenant = new Tenant;
        $tenant->setAttribute('stripe_id', $stripeId);
        $tenant->setAttribute('status', Tenant::STATUS_ACTIVE);

        return $tenant;
    }

    // -------------------------------------------------------------------------
    // Service — getOpenInvoicePaymentUrl
    // -------------------------------------------------------------------------

    public function test_returns_hosted_invoice_url_for_open_invoice(): void
    {
        $service = new TestableBillingService;
        $service->fakeStripe = $this->fakeStripeReturningInvoices([
            Invoice::constructFrom([
                'id' => 'in_test',
                'amount_remaining' => 1990,
                'hosted_invoice_url' => 'https://invoice.stripe.test/in_test',
            ]),
        ]);

        $url = $service->getOpenInvoicePaymentUrl($this->tenantWithStripeId());

        $this->assertSame('https://invoice.stripe.test/in_test', $url);
    }

    public function test_returns_null_when_no_open_invoice(): void
    {
        $service = new TestableBillingService;
        $service->fakeStripe = $this->fakeStripeReturningInvoices([]);

        $this->assertNull($service->getOpenInvoicePaymentUrl($this->tenantWithStripeId()));
    }

    public function test_returns_null_when_invoice_is_fully_paid(): void
    {
        $service = new TestableBillingService;
        $service->fakeStripe = $this->fakeStripeReturningInvoices([
            Invoice::constructFrom([
                'id' => 'in_paid',
                'amount_remaining' => 0,
                'hosted_invoice_url' => 'https://invoice.stripe.test/in_paid',
            ]),
        ]);

        $this->assertNull($service->getOpenInvoicePaymentUrl($this->tenantWithStripeId()));
    }

    public function test_returns_null_when_tenant_has_no_stripe_id(): void
    {
        $service = new TestableBillingService;
        // Sem stripe_id o método retorna antes de tocar no Stripe.
        $tenant = new Tenant;
        $tenant->setAttribute('stripe_id', null);

        $this->assertNull($service->getOpenInvoicePaymentUrl($tenant));
    }

    // -------------------------------------------------------------------------
    // Controller — retryPayment
    // -------------------------------------------------------------------------

    public function test_retry_returns_invoice_url_when_available(): void
    {
        $tenant = $this->tenantWithStripeId();
        app(Tenancy::class)->tenant = $tenant;

        /** @var TenantBillingService&MockInterface $billing */
        $billing = Mockery::mock(TenantBillingService::class);
        $billing->shouldReceive('getOpenInvoicePaymentUrl')
            ->once()
            ->andReturn('https://invoice.stripe.test/in_test');

        $response = (new DunningController($billing))->retryPayment();
        $json = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('https://invoice.stripe.test/in_test', $json['data']['invoice_url']);
    }

    public function test_retry_returns_422_when_no_open_invoice(): void
    {
        $tenant = $this->tenantWithStripeId();
        app(Tenancy::class)->tenant = $tenant;

        /** @var TenantBillingService&MockInterface $billing */
        $billing = Mockery::mock(TenantBillingService::class);
        $billing->shouldReceive('getOpenInvoicePaymentUrl')->once()->andReturnNull();

        $response = (new DunningController($billing))->retryPayment();

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_retry_returns_conflict_for_cancelled_tenant(): void
    {
        $tenant = new Tenant;
        $tenant->setAttribute('status', TenantStatus::CANCELLED->value);
        app(Tenancy::class)->tenant = $tenant;

        /** @var TenantBillingService&MockInterface $billing */
        $billing = Mockery::mock(TenantBillingService::class);
        $billing->shouldReceive('getOpenInvoicePaymentUrl')->never();

        $response = (new DunningController($billing))->retryPayment();

        $this->assertSame(409, $response->getStatusCode());
    }
}
