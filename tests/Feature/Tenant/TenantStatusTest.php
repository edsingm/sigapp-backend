<?php

namespace Tests\Feature\Tenant;

use App\Http\Controllers\Api\V1\TenantStatusController;
use Tests\TestCase;

class TenantStatusTest extends TestCase
{
    public function test_controller_existe_e_tem_metodo_index(): void
    {
        $reflection = new \ReflectionClass(TenantStatusController::class);

        $this->assertTrue($reflection->hasMethod('index'));
    }

    public function test_endpoint_exige_autenticacao(): void
    {
        $response = $this->getJson('/api/v1/tenant-status');

        $response->assertStatus(401);
    }
}
