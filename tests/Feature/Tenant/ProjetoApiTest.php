<?php

namespace Tests\Feature\Tenant;

use App\Http\Controllers\Api\V1\Tenant\ProjetoController;
use Tests\TestCase;

class ProjetoApiTest extends TestCase
{
    public function test_controller_existe(): void
    {
        $reflection = new \ReflectionClass(ProjetoController::class);

        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->hasMethod('store'));
        $this->assertTrue($reflection->hasMethod('show'));
        $this->assertTrue($reflection->hasMethod('update'));
        $this->assertTrue($reflection->hasMethod('cancel'));
        $this->assertTrue($reflection->hasMethod('markReady'));
    }

    public function test_controller_tem_dependencias_injetadas(): void
    {
        $reflection = new \ReflectionClass(ProjetoController::class);

        $this->assertTrue($reflection->hasMethod('__construct'));

        $constructor = $reflection->getMethod('__construct');
        $params = $constructor->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params));
    }
}
