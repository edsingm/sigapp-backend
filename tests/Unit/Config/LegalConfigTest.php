<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Services\Signup\TenantSignupService;
use Tests\TestCase;

class LegalConfigTest extends TestCase
{
    public function test_document_keys_point_to_canonical_legal_paths(): void
    {
        $keys = config('legal.document_keys');

        $this->assertIsArray($keys);
        $this->assertNotEmpty($keys);
        $this->assertContains('signup_usage_contract', $keys);

        foreach ($keys as $key) {
            $this->assertIsString($key);

            $document = config('legal.'.$key);
            $this->assertIsArray($document, $key);
            $this->assertSame($key, $document['key'] ?? null);
            $this->assertNotSame('', (string) ($document['title'] ?? ''));
            $this->assertNotSame('', (string) ($document['version'] ?? ''));
            $this->assertNotSame('', (string) ($document['effective_at'] ?? ''));
            $this->assertMatchesRegularExpression('/\A\/legal\/[a-z0-9\-]+\z/', (string) ($document['url'] ?? ''));
            $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', (string) ($document['hash'] ?? ''));
            $this->assertIsBool($document['requires_acceptance'] ?? null);
        }

        $this->assertTrue((bool) config('legal.signup_usage_contract.requires_acceptance'));
        $this->assertTrue((bool) config('legal.privacy_policy.requires_acceptance'));
        $this->assertFalse((bool) config('legal.cookies_policy.requires_acceptance'));
        $this->assertFalse((bool) config('legal.lgpd.requires_acceptance'));
    }

    public function test_signup_usage_contract_no_longer_points_to_juridico(): void
    {
        $url = (string) config('legal.signup_usage_contract.url');

        $this->assertSame('/legal/termos-de-uso', $url);
        $this->assertStringNotContainsString('/juridico/', $url);
    }

    public function test_signup_service_fallback_uses_canonical_legal_path(): void
    {
        $service = app(TenantSignupService::class);

        config(['legal.signup_usage_contract' => []]);

        $contract = $service->getSignupUsageContractConfig();

        $this->assertSame('/legal/termos-de-uso', $contract['url']);
        $this->assertStringNotContainsString('/juridico/', (string) $contract['url']);
    }
}
