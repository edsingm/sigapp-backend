<?php

namespace Tests\Feature\Jobs;

use App\Enums\TenantStatus;
use App\Jobs\CreateFullTenantJob;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
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

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
    }

    public function test_job_tem_tries_backoff_e_unique_for(): void
    {
        $tenant = $this->makeTenant();
        $job = new CreateFullTenantJob($tenant);

        $refl = new \ReflectionClass($job);
        $triesAttr = $refl->getAttributes(Tries::class);
        $backoffAttr = $refl->getAttributes(Backoff::class);

        $this->assertSame(3, $triesAttr[0]->getArguments()[0]);
        $this->assertSame(60, $backoffAttr[0]->getArguments()[0]);
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
        $this->assertSame(TenantStatus::SETUP_FAILED->value, $tenant->getAttribute('status'));
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
