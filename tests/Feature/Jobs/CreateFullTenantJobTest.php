<?php

namespace Tests\Feature\Jobs;

use App\Enums\TenantStatus;
use App\Jobs\CreateFullTenantJob;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateFullTenantJobTest extends TestCase
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

    private function makeTenant(array $attrs = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => 'Empresa Teste',
            'slug' => 'empresa-teste-'.uniqid(),
            'status' => Tenant::STATUS_PENDING,
            'plan_id' => $this->plan->id,
            'admin_name' => 'Admin Teste',
            'admin_email' => 'admin@teste.com',
            'admin_password' => bcrypt('password123'),
            'database_created' => false,
        ], $attrs));
    }

    public function test_job_implementa_should_be_unique(): void
    {
        $tenant = $this->makeTenant();
        $job = new CreateFullTenantJob($tenant);

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class, $job);
    }

    public function test_job_tem_tries_backoff_e_unique_for(): void
    {
        $tenant = $this->makeTenant();
        $job = new CreateFullTenantJob($tenant);

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->backoff);
        $this->assertSame(900, $job->uniqueFor);
    }

    public function test_unique_id_baseado_no_tenant_key(): void
    {
        $tenant = $this->makeTenant();
        $job = new CreateFullTenantJob($tenant);

        $this->assertSame('tenant-provisioning:'.$tenant->getKey(), $job->uniqueId());
    }

    public function test_failed_marca_tenant_como_setup_failed(): void
    {
        $tenant = $this->makeTenant();
        $job = new CreateFullTenantJob($tenant);

        $job->failed(new \RuntimeException('Database creation failed'));

        $tenant->refresh();
        $this->assertSame(TenantStatus::SETUP_FAILED->value, $tenant->status);
    }

    public function test_job_pode_ser_dispatchado(): void
    {
        Queue::fake();

        $tenant = $this->makeTenant();

        CreateFullTenantJob::dispatch($tenant);

        Queue::assertPushed(CreateFullTenantJob::class, function ($job) use ($tenant) {
            return $job->tenant->id === $tenant->id;
        });
    }
}
