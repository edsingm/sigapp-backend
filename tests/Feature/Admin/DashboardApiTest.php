<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\V1\Admin\DashboardController;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    public function test_controller_existe(): void
    {
        $reflection = new \ReflectionClass(DashboardController::class);

        $this->assertTrue($reflection->hasMethod('index'));
    }

    public function test_controller_tem_dependencias_injetadas(): void
    {
        $reflection = new \ReflectionClass(DashboardController::class);

        $this->assertTrue($reflection->hasMethod('__construct'));

        $constructor = $reflection->getMethod('__construct');
        $params = $constructor->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params));
    }
}
