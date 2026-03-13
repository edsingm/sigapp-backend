<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class PublicTenantDiscoverDeprecatedTest extends TestCase
{
    public function test_tenant_discover_route_is_removed(): void
    {
        $response = $this->getJson('/api/v1/tenant/discover?email=test@example.com');

        $response
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonMissingPath('data');
    }
}
