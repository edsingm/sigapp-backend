<?php

namespace Tests\Unit\Middleware;

use App\Enums\TenantStatus;
use App\Http\Middleware\CheckSubscriptionStatus;
use Illuminate\Http\Request;
use Tests\TestCase;

class CheckSubscriptionStatusTest extends TestCase
{
    public function test_middleware_existe_e_tem_handle(): void
    {
        $reflection = new \ReflectionClass(CheckSubscriptionStatus::class);

        $this->assertTrue($reflection->hasMethod('handle'));
    }

    public function test_middleware_aceita_request_e_closure(): void
    {
        $method = new \ReflectionMethod(CheckSubscriptionStatus::class, 'handle');

        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('request', $params[0]->getName());
        $this->assertSame('next', $params[1]->getName());
    }

    public function test_tenant_status_enum_tem_todos_os_casos(): void
    {
        $cases = TenantStatus::cases();

        $values = array_map(fn ($case) => $case->value, $cases);

        $this->assertContains('pending', $values);
        $this->assertContains('active', $values);
        $this->assertContains('suspended', $values);
        $this->assertContains('cancelled', $values);
        $this->assertContains('setup_failed', $values);
    }

    public function test_tenant_status_label_retorna_string(): void
    {
        foreach (TenantStatus::cases() as $case) {
            $this->assertNotEmpty($case->label());
        }
    }
}
