<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RefreshTenantStatsJob;
use App\Services\TenantStatusService;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
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

        $service = new class($expectedStats) extends TenantStatusService
        {
            public bool $refreshStatsCalled = false;

            /**
             * @param  array{total_tenants: int, total_terrenos: int, total_projetos: int, total_usuarios: int}  $stats
             */
            public function __construct(
                private readonly array $stats,
            ) {}

            public function refreshStats(): array
            {
                $this->refreshStatsCalled = true;

                return $this->stats;
            }
        };

        Log::shouldReceive('info')->twice();

        $job = new RefreshTenantStatsJob;
        $job->handle($service);

        $this->assertTrue($service->refreshStatsCalled);
    }

    public function test_failed_loga_erro(): void
    {
        Log::shouldReceive('error')->once()->with(
            'RefreshTenantStatsJob falhou definitivamente',
            Mockery::on(fn (array $context) => $context['error'] === 'Test error')
        );

        $job = new RefreshTenantStatsJob;
        $job->failed(new \RuntimeException('Test error'));

        $this->assertInstanceOf(RefreshTenantStatsJob::class, $job);
    }

    public function test_job_tem_tries_e_timeout(): void
    {
        $job = new RefreshTenantStatsJob;

        $refl = new \ReflectionClass($job);
        $triesAttr = $refl->getAttributes(Tries::class);
        $timeoutAttr = $refl->getAttributes(Timeout::class);

        $this->assertSame(3, $triesAttr[0]->getArguments()[0]);
        $this->assertSame(120, $timeoutAttr[0]->getArguments()[0]);
    }
}
