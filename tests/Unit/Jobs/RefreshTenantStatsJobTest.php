<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RefreshTenantStatsJob;
use App\Services\TenantStatusService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class RefreshTenantStatsJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_chama_refresh_stats(): void
    {
        $expectedStats = [
            'total_tenants' => 5,
            'total_terrenos' => 100,
            'total_projetos' => 50,
            'total_usuarios' => 20,
        ];

        $service = Mockery::mock(TenantStatusService::class);
        $service->shouldReceive('refreshStats')->once()->andReturn($expectedStats);

        Log::shouldReceive('info')->twice();

        $job = new RefreshTenantStatsJob;
        $job->handle($service);

        $this->assertTrue(true); // O teste valida que o método foi chamado via Mockery
    }

    public function test_failed_loga_erro(): void
    {
        Log::shouldReceive('error')->once()->with(
            'RefreshTenantStatsJob falhou definitivamente',
            Mockery::on(fn (array $context) => $context['error'] === 'Test error')
        );

        $job = new RefreshTenantStatsJob;
        $job->failed(new \RuntimeException('Test error'));

        $this->assertTrue(true); // O teste valida que o log foi chamado via Mockery
    }

    public function test_job_tem_tries_e_timeout(): void
    {
        $job = new RefreshTenantStatsJob;

        $this->assertSame(3, $job->tries);
        $this->assertSame(120, $job->timeout);
    }
}
