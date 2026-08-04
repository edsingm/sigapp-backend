<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Testing\Fakes\QueueFake;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function tearDown(): void
    {
        $queue = $this->app->bound('queue') ? $this->app->make('queue') : null;

        if ($queue instanceof QueueFake) {
            $queue->releaseUniqueJobLocks();
        }

        parent::tearDown();
    }
}
