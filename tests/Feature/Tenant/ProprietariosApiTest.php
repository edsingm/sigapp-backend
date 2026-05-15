<?php

namespace Tests\Feature\Tenant;

use App\Http\Controllers\Api\V1\Tenant\ProprietariosController;
use Tests\TestCase;

class ProprietariosApiTest extends TestCase
{
    public function test_controller_existe(): void
    {
        $reflection = new \ReflectionClass(ProprietariosController::class);

        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->hasMethod('store'));
        $this->assertTrue($reflection->hasMethod('show'));
        $this->assertTrue($reflection->hasMethod('update'));
        $this->assertTrue($reflection->hasMethod('destroy'));
    }

    public function test_controller_tem_dependencias_injetadas(): void
    {
        $reflection = new \ReflectionClass(ProprietariosController::class);

        $this->assertTrue($reflection->hasMethod('__construct'));

        $constructor = $reflection->getMethod('__construct');
        $params = $constructor->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params));
    }
}
