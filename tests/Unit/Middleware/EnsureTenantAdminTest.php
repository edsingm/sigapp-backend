<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureTenantAdmin;
use Tests\TestCase;

class EnsureTenantAdminTest extends TestCase
{
    public function test_middleware_existe_e_tem_handle(): void
    {
        $reflection = new \ReflectionClass(EnsureTenantAdmin::class);

        $this->assertTrue($reflection->hasMethod('handle'));
    }

    public function test_middleware_aceita_request_e_closure(): void
    {
        $method = new \ReflectionMethod(EnsureTenantAdmin::class, 'handle');

        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('request', $params[0]->getName());
        $this->assertSame('next', $params[1]->getName());
    }
}
