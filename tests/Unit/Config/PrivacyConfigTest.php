<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class PrivacyConfigTest extends TestCase
{
    public function test_retention_defaults_match_sig26_policy(): void
    {
        $this->assertSame('dpo@sigapp.com.br', config('privacy.dpo_email'));
        $this->assertSame(180, config('privacy.consent_log_retention_days'));
        $this->assertSame(90, config('privacy.cancelled_tenant_retention_days'));
        $this->assertSame(90, config('privacy.soft_delete_retention_days'));
        $this->assertSame(90, config('privacy.ai_log_retention_days'));
        $this->assertSame(24, config('privacy.export_ttl_hours'));
        $this->assertSame(5, config('privacy.privacy_request_retention_years'));
        $this->assertFalse((bool) config('privacy.auto_wipe_enabled'));
        $this->assertSame(30, config('privacy.wipe_first_notice_days_before'));
        $this->assertSame(7, config('privacy.wipe_final_notice_days_before'));
    }

    public function test_subprocessors_are_keyed_inventories_without_marketing_pixels(): void
    {
        $subprocessors = config('privacy.subprocessors');

        $this->assertIsArray($subprocessors);
        $this->assertNotEmpty($subprocessors);

        $keys = [];

        foreach ($subprocessors as $processor) {
            $this->assertIsArray($processor);
            $this->assertNotSame('', (string) ($processor['key'] ?? ''));
            $this->assertNotSame('', (string) ($processor['name'] ?? ''));
            $this->assertNotSame('', (string) ($processor['purpose'] ?? ''));
            $this->assertIsArray($processor['data_categories'] ?? null);
            $this->assertNotEmpty($processor['data_categories']);
            $this->assertNotSame('', (string) ($processor['location'] ?? ''));
            $this->assertSame('operator', $processor['role'] ?? null);

            $keys[] = $processor['key'];
        }

        $this->assertContains('stripe', $keys);
        $this->assertContains('resend', $keys);
        $this->assertContains('opencode_go', $keys);
        $this->assertNotContains('posthog', $keys);
        $this->assertNotContains('intercom', $keys);
        $this->assertSame($keys, array_values(array_unique($keys)));
    }
}
