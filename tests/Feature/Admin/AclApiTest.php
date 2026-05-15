<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\V1\Admin\AclController;
use Tests\TestCase;

class AclApiTest extends TestCase
{
    public function test_controller_existe(): void
    {
        $reflection = new \ReflectionClass(AclController::class);

        $this->assertTrue($reflection->hasMethod('catalog'));
        $this->assertTrue($reflection->hasMethod('planRoleMatrix'));
    }

    public function test_controller_tem_dependencias_injetadas(): void
    {
        $reflection = new \ReflectionClass(AclController::class);

        $this->assertTrue($reflection->hasMethod('__construct'));

        $constructor = $reflection->getMethod('__construct');
        $params = $constructor->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params));
    }
}
