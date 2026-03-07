<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class PublicTenantDiscoverDeprecatedTest extends TestCase
{
    public function test_tenant_discover_returns_gone_without_tenant_enumeration(): void
    {
        $response = $this->getJson('/api/v1/tenant/discover?email=test@example.com');

        $response
            ->assertStatus(410)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'DEPRECATED_ENDPOINT')
            ->assertJsonMissingPath('data');
    }
}
