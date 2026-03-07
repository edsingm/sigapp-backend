<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class CentralAuthLoginDeprecatedTest extends TestCase
{
    public function test_central_auth_login_endpoint_is_deprecated_and_points_to_central_login_flow(): void
    {
        $response = $this->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->postJson('/api/v1/auth/login', []);

        $response
            ->assertStatus(410)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'DEPRECATED_ENDPOINT');
    }
}
