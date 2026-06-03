<?php

namespace Tests\Unit;

use App\Services\AiDataRedactor;
use App\Services\AiProviderRouter;
use App\Services\AiTelemetryService;
use Mockery;
use Tests\TestCase;

class AiServicesAndMiddlewareTest extends TestCase
{
    // ── AiDataRedactor Tests ──────────────────────────────────────────────────

    public function test_redacts_cpf(): void
    {
        $redactor = new AiDataRedactor;
        $result = $redactor->redactText('O CPF do cliente é 123.456.789-00');

        $this->assertStringContainsString('***.***.***-**', $result);
        $this->assertStringNotContainsString('123.456.789-00', $result);
    }

    public function test_redacts_email(): void
    {
        $redactor = new AiDataRedactor;
        $result = $redactor->redactText('Email: joao@email.com');

        $this->assertStringContainsString('[email redacted]', $result);
        $this->assertStringNotContainsString('joao@email.com', $result);
    }

    public function test_redacts_phone(): void
    {
        $redactor = new AiDataRedactor;
        $result = $redactor->redactText('Telefone: (11) 99999-8888');

        $this->assertStringContainsString('[telefone redacted]', $result);
    }

    public function test_redacts_payload_fields(): void
    {
        $redactor = new AiDataRedactor;
        $result = $redactor->redactPayload([
            'nome' => 'João',
            'email' => 'joao@email.com',
            'cpf' => '123.456.789-00',
        ]);

        $this->assertEquals('João', $result['nome']);
        $this->assertEquals('[redacted]', $result['email']);
        $this->assertEquals('[redacted]', $result['cpf']);
    }

    public function test_redacts_nested_payload_fields(): void
    {
        $redactor = new AiDataRedactor;
        $result = $redactor->redactPayload([
            'owner' => [
                'email' => 'test@test.com',
                'phone' => '11999999999',
            ],
        ]);

        $this->assertEquals('[redacted]', $result['owner']['email']);
        $this->assertEquals('[redacted]', $result['owner']['phone']);
    }

    public function test_leaves_clean_text_unchanged(): void
    {
        $redactor = new AiDataRedactor;
        $result = $redactor->redactText('Este é um texto normal sem dados sensíveis');

        $this->assertEquals('Este é um texto normal sem dados sensíveis', $result);
    }

    // ── AiProviderRouter Tests ────────────────────────────────────────────────

    public function test_provider_router_returns_primary_agent(): void
    {
        $router = new AiProviderRouter;
        $result = $router->getAgentWithFallback();

        $this->assertArrayHasKey('agent', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertFalse($result['isFallback']);
        $this->assertEquals('openrouter', $result['provider']);
    }

    public function test_provider_router_returns_fallback_agent(): void
    {
        $router = new AiProviderRouter;
        $result = $router->getFallbackAgent();

        $this->assertArrayHasKey('agent', $result);
        $this->assertArrayHasKey('isFallback', $result);
        $this->assertTrue($result['isFallback']);
    }

    public function test_provider_router_records_attempts(): void
    {
        $router = new AiProviderRouter;
        $router->recordAttempt('openrouter', 'glm-4-air', false, 'Error');

        $attempts = $router->getAttempts();

        $this->assertCount(1, $attempts);
        $this->assertFalse($attempts[0]['success']);
        $this->assertEquals('Error', $attempts[0]['error']);
    }

    public function test_provider_router_records_successful_attempt(): void
    {
        $router = new AiProviderRouter;
        $router->recordAttempt('openrouter', 'glm-4-air', true);

        $attempts = $router->getAttempts();

        $this->assertCount(1, $attempts);
        $this->assertTrue($attempts[0]['success']);
    }

    // ── AiTelemetryService Tests ──────────────────────────────────────────────

    public function test_estimate_cost_openrouter_free(): void
    {
        $service = app(AiTelemetryService::class);
        $cost = $service->estimateCost('openrouter', 'glm-4-air', 1000, 500);

        $this->assertIsFloat($cost);
        $this->assertGreaterThanOrEqual(0.0, $cost);
    }

    public function test_estimate_cost_unknown_provider(): void
    {
        $service = app(AiTelemetryService::class);
        $cost = $service->estimateCost('unknown', 'model', 1000000, 1000000);

        $this->assertEquals(0.0, $cost, '', 0.0001);
    }

    public function test_estimate_cost_anthropic(): void
    {
        $service = app(AiTelemetryService::class);
        $cost = $service->estimateCost('anthropic', 'claude-sonnet-4-6', 1000000, 500000);

        // Expected: 3.00 + 7.50 = 10.50
        $this->assertEqualsWithDelta(10.50, $cost, 0.01);
    }

    public function test_get_tenant_monthly_cost_returns_zero(): void
    {
        $service = app(AiTelemetryService::class);
        $cost = $service->getTenantMonthlyCost();

        // Returns 0 when tenancy is not initialized
        $this->assertEquals(0.0, $cost);
    }

    public function test_budget_status_has_default(): void
    {
        $service = app(AiTelemetryService::class);
        $status = $service->getBudgetStatus();

        $this->assertArrayHasKey('budget_usd', $status);
        $this->assertEquals(10.0, $status['budget_usd']);
        $this->assertArrayHasKey('spent_usd', $status);
        $this->assertEquals(0.0, $status['spent_usd']);
        $this->assertFalse($status['exceeded']);
    }

    public function test_budget_not_exceeded_when_empty(): void
    {
        $service = app(AiTelemetryService::class);

        $this->assertFalse($service->hasExceededBudget(100.0));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
