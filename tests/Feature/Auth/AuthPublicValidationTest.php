<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthPublicValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_select_tenant_requires_broker_session_and_tenant(): void
    {
        $response = $this
            ->withHeader('Host', 'localhost')
            ->postJson('/api/v1/auth/select-tenant', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['broker_session_id', 'tenant_id']);
    }

    public function test_exchange_ticket_requires_ticket(): void
    {
        $this->withoutMiddleware();

        $response = $this
            ->withHeader('Host', 'localhost')
            ->postJson('/api/v1/auth/exchange-ticket', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket']);
    }

    public function test_forgot_password_requires_valid_email(): void
    {
        $response = $this
            ->withHeader('Host', 'localhost')
            ->postJson('/api/v1/auth/password/forgot', ['email' => 'invalido']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_requires_tenant_identifier_in_central_context(): void
    {
        $response = $this
            ->withHeader('Host', 'localhost')
            ->postJson('/api/v1/auth/password/reset', [
                'email' => 'user@test.com',
                'token' => 'token-123',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant_identifier']);
    }
}
