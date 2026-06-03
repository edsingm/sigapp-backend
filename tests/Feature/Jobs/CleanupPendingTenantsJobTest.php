<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CleanupPendingTenantsJob;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Billing\TenantBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
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

        $billingService = new class extends TenantBillingService {};

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

        $billingService = new class extends TenantBillingService {};

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

        $billingService = new class extends TenantBillingService
        {
            public function getSignupCheckoutSessionId(Tenant $tenant): ?string
            {
                return null;
            }
        };

        $job = new CleanupPendingTenantsJob;
        $job->handle($billingService);

        $this->assertDatabaseHas('tenants', ['id' => $tenantId]);
    }

    public function test_failed_loga_erro_sem_lancar_excecao(): void
    {
        Log::spy();

        $job = new CleanupPendingTenantsJob;
        $job->failed(new RuntimeException('Stripe API timeout'));

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'CleanupPendingTenantsJob falhou definitivamente'
                    && $context['error'] === 'Stripe API timeout'
                    && $context['exception_class'] === RuntimeException::class;
            });
    }

    public function test_job_tem_tries_timeout_e_backoff_configurados(): void
    {
        $job = new CleanupPendingTenantsJob;

        $this->assertSame(3, $job->tries);
        $this->assertSame(300, $job->timeout);
        $this->assertSame([60, 300, 900], $job->backoff);
    }
}
