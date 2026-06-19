<?php

namespace Tests\Unit\Services\Billing;

use App\Models\Central\Tenant;
use App\Services\Billing\TenantBillingService;
use Tests\TestCase;

/**
 * Stub de Tenant que captura os argumentos de billingPortalUrl sem chamar o Stripe.
 */
class TenantPortalStub extends Tenant
{
    public array $capturedOptions = [];

    public ?string $capturedReturnUrl = null;

    public function billingPortalUrl(?string $returnUrl = null, array $options = []): string
    {
        $this->capturedReturnUrl = $returnUrl;
        $this->capturedOptions = $options;

        return 'https://portal.stripe.test/session';
    }
}

class BillingPortalConfigurationTest extends TestCase
{
    public function test_passes_configuration_when_portal_configuration_id_is_set(): void
    {
        config(['cashier.portal_configuration_id' => 'bpc_restricted']);

        $tenant = new TenantPortalStub;
        $service = new TenantBillingService;

        $url = $service->createBillingPortalUrl($tenant, 'https://app.test/billing');

        $this->assertSame('https://portal.stripe.test/session', $url);
        $this->assertSame('https://app.test/billing', $tenant->capturedReturnUrl);
        $this->assertSame(['configuration' => 'bpc_restricted'], $tenant->capturedOptions);
    }

    public function test_omits_configuration_when_not_set(): void
    {
        config(['cashier.portal_configuration_id' => null]);

        $tenant = new TenantPortalStub;
        $service = new TenantBillingService;

        $service->createBillingPortalUrl($tenant, 'https://app.test/billing');

        $this->assertSame([], $tenant->capturedOptions);
    }
}
