<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CleanupPendingTenantsJob;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Billing\TenantBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CleanupPendingTenantsJobTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::create([
            'name' => 'Plano Teste',
            'slug' => 'teste',
            'description' => 'Plano para testes',
            'stripe_price_id' => 'price_test_123',
            'price' => 9900,
            'trial_days' => 7,
            'is_active' => true,
        ]);
    }

    private function makePendingTenant(array $attrs = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => 'Empresa Pending',
            'slug' => 'empresa-pending-'.uniqid(),
            'status' => Tenant::STATUS_PENDING,
            'plan_id' => $this->plan->id,
            'admin_name' => 'Admin',
            'admin_email' => 'admin@pending.com',
            'admin_password' => bcrypt('password123'),
            'database_created' => false,
            'created_at' => now()->subDays(2), // Expirado (>24h)
        ], $attrs));
    }

    public function test_nao_remove_tenant_pending_recente(): void
    {
        $tenant = $this->makePendingTenant([
            'created_at' => now()->subHours(12), // Não expirado (<24h)
        ]);
        $tenantId = $tenant->id;

        $billingService = Mockery::mock(TenantBillingService::class);
        $this->app->instance(TenantBillingService::class, $billingService);

        $job = new CleanupPendingTenantsJob;
        $job->handle($billingService);

        $this->assertDatabaseHas('tenants', ['id' => $tenantId]);
    }

    public function test_nao_remove_tenant_active(): void
    {
        $tenant = $this->makePendingTenant([
            'status' => Tenant::STATUS_ACTIVE,
            'created_at' => now()->subDays(5),
        ]);
        $tenantId = $tenant->id;

        $billingService = Mockery::mock(TenantBillingService::class);
        $this->app->instance(TenantBillingService::class, $billingService);

        $job = new CleanupPendingTenantsJob;
        $job->handle($billingService);

        $this->assertDatabaseHas('tenants', ['id' => $tenantId]);
    }

    public function test_nao_remove_tenant_com_assinatura(): void
    {
        $tenant = $this->makePendingTenant([
            'stripe_subscription_id' => 'sub_123',
        ]);
        $tenantId = $tenant->id;

        $billingService = Mockery::mock(TenantBillingService::class);
        $billingService->shouldReceive('getSignupCheckoutSessionId')
            ->andReturn(null);
        $this->app->instance(TenantBillingService::class, $billingService);

        $job = new CleanupPendingTenantsJob;
        $job->handle($billingService);

        $this->assertDatabaseHas('tenants', ['id' => $tenantId]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
