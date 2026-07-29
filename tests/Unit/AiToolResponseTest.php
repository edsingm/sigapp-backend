<?php

namespace Tests\Unit;

use App\Services\Ai\Tools\AiToolResponse;
use Tests\TestCase;

class AiToolResponseTest extends TestCase
{
    public function test_ok_envelope_is_compact_json(): void
    {
        $json = AiToolResponse::ok(['items' => [1]], 'ok');
        $payload = json_decode($json, true);

        $this->assertTrue($payload['ok']);
        $this->assertSame(AiToolResponse::OK, $payload['code']);
        $this->assertSame('ok', $payload['message']);
        $this->assertSame([1], $payload['data']['items']);
        $this->assertStringNotContainsString("\n", $json);
    }

    public function test_empty_is_ok_true_with_empty_code(): void
    {
        $payload = json_decode(AiToolResponse::empty('Nada encontrado'), true);

        $this->assertTrue($payload['ok']);
        $this->assertSame(AiToolResponse::EMPTY, $payload['code']);
        $this->assertSame('Nada encontrado', $payload['message']);
    }

    public function test_denied_and_plan_denied_codes(): void
    {
        $denied = json_decode(AiToolResponse::denied('sem permissão'), true);
        $plan = json_decode(AiToolResponse::planDenied('plano não inclui'), true);

        $this->assertFalse($denied['ok']);
        $this->assertSame(AiToolResponse::DENIED, $denied['code']);
        $this->assertFalse($plan['ok']);
        $this->assertSame(AiToolResponse::PLAN_DENIED, $plan['code']);
    }

    public function test_list_meta_and_clamp_limit(): void
    {
        $meta = AiToolResponse::listMeta(total: 100, returned: 10, limit: 10);

        $this->assertSame([
            'total' => 100,
            'returned' => 10,
            'limit' => 10,
            'has_more' => true,
        ], $meta);

        $this->assertSame(10, AiToolResponse::clampLimit(null));
        $this->assertSame(10, AiToolResponse::clampLimit(0)); // 0 cai no default
        $this->assertSame(50, AiToolResponse::clampLimit(999));
        $this->assertSame(20, AiToolResponse::clampLimit(20, default: 10, max: 50));
    }
}
