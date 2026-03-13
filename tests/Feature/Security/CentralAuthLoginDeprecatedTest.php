<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class CentralAuthLoginDeprecatedTest extends TestCase
{
    public function test_central_auth_login_endpoint_uses_the_canonical_route_contract(): void
    {
        $response = $this->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->postJson('/api/v1/auth/login', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
