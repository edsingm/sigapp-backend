<?php

namespace Tests;

use App\Encryption\TenantEncrypter;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Testing\Fakes\QueueFake;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Parte da suíte usa as tabelas tenant no SQLite central, sem inicializar
        // stancl/tenancy. Ainda assim, casts de PII devem exercitar cifra real.
        $this->app->make(TenantEncrypter::class)->configure(random_bytes(32));
    }

    protected function tearDown(): void
    {
        $queue = $this->app->bound('queue') ? $this->app->make('queue') : null;

        if ($queue instanceof QueueFake) {
            $queue->releaseUniqueJobLocks();
        }

        parent::tearDown();
    }
}
