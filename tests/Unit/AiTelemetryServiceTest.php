<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Central\Tenant;
use App\Models\Tenant\AiRequestLog;
use App\Repositories\Contracts\AiTelemetryRepositoryInterface;
use App\Services\Ai\Tools\AiTelemetryService;
use App\Services\PlanMatrixService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verifica que a contagem de tokens e o cálculo de custo estimado
 * estão corretos para todos os provedores suportados.
 */
class AiTelemetryServiceTest extends TestCase
{
    private AiTelemetryRepositoryInterface&MockInterface $repo;

    private PlanMatrixService&MockInterface $planMatrix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->mockTelemetryRepository();
        $this->planMatrix = $this->mockPlanMatrix();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── estimateCost: Anthropic ───────────────────────────────────────────────

    public function test_anthropic_cost_full_million_tokens(): void
    {
        // input: $3.00/M, output: $15.00/M
        // 1_000_000 prompt + 500_000 completion = $3.00 + $7.50 = $10.50
        $service = $this->makeService();
        $cost = $service->estimateCost('anthropic', 'claude-sonnet-4-6', 1_000_000, 500_000);

        $this->assertEqualsWithDelta(10.50, $cost, 0.000001);
    }

    public function test_anthropic_cost_only_prompt_tokens(): void
    {
        // 1_000_000 prompt + 0 completion = $3.00
        $service = $this->makeService();
        $cost = $service->estimateCost('anthropic', 'claude-opus-4', 1_000_000, 0);

        $this->assertEqualsWithDelta(3.00, $cost, 0.000001);
    }

    public function test_anthropic_cost_only_completion_tokens(): void
    {
        // 0 prompt + 1_000_000 completion = $15.00
        $service = $this->makeService();
        $cost = $service->estimateCost('anthropic', 'claude-sonnet-4-6', 0, 1_000_000);

        $this->assertEqualsWithDelta(15.00, $cost, 0.000001);
    }

    public function test_anthropic_cost_small_request(): void
    {
        // 500 prompt ($0.0015) + 200 completion ($0.003) = $0.0045
        $service = $this->makeService();
        $cost = $service->estimateCost('anthropic', 'claude-haiku', 500, 200);

        $this->assertEqualsWithDelta(0.0045, $cost, 0.000001);
    }

    // ── estimateCost: OpenAI ─────────────────────────────────────────────────

    public function test_openai_cost_standard_request(): void
    {
        // input: $2.50/M, output: $10.00/M
        // 400_000 prompt + 100_000 completion = $1.00 + $1.00 = $2.00
        $service = $this->makeService();
        $cost = $service->estimateCost('openai', 'gpt-4o', 400_000, 100_000);

        $this->assertEqualsWithDelta(2.00, $cost, 0.000001);
    }

    public function test_openai_cost_one_million_each(): void
    {
        // 1_000_000 + 1_000_000 = $2.50 + $10.00 = $12.50
        $service = $this->makeService();
        $cost = $service->estimateCost('openai', 'gpt-4o', 1_000_000, 1_000_000);

        $this->assertEqualsWithDelta(12.50, $cost, 0.000001);
    }

    // ── estimateCost: DeepSeek (cache hit/miss) ───────────────────────────────

    public function test_deepseek_cost_all_cache_miss(): void
    {
        // Nenhum cache hit: 1_000_000 cache-miss tokens + 1_000_000 completion
        // cache_miss: $0.14/M → $0.14
        // output: $0.28/M → $0.28
        // total: $0.42
        $service = $this->makeService();
        $cost = $service->estimateCost('deepseek', 'deepseek-v3', 1_000_000, 1_000_000, 0);

        $this->assertEqualsWithDelta(0.42, $cost, 0.000001);
    }

    public function test_deepseek_cost_all_cache_hit(): void
    {
        // 100% do input veio do cache: cache-miss = 0, cache-hit = 1_000_000
        // cache_hit: $0.0028/M → $0.0028
        // output: $0.28/M → $0.0028 (100_000 tokens)
        // total: $0.0028 + $0.028 = $0.0308
        $service = $this->makeService();
        $cost = $service->estimateCost('deepseek', 'deepseek-v3', 1_000_000, 100_000, 1_000_000);

        // input: (0 miss * 0.14) + (1M hit * 0.0028) = $0.0028
        // output: (100K / 1M) * 0.28 = $0.028
        $this->assertEqualsWithDelta(0.0308, $cost, 0.000001);
    }

    public function test_deepseek_cost_partial_cache_hit(): void
    {
        // 1_000_000 prompt total, 800_000 veio do cache (hit), 200_000 cache miss
        // miss: (200_000/1M) * 0.14 = $0.028
        // hit:  (800_000/1M) * 0.0028 = $0.00224
        // output: (500_000/1M) * 0.28 = $0.14
        // total: 0.028 + 0.00224 + 0.14 = $0.17024
        $service = $this->makeService();
        $cost = $service->estimateCost('deepseek', 'deepseek-v3', 1_000_000, 500_000, 800_000);

        $this->assertEqualsWithDelta(0.17024, $cost, 0.000001);
    }

    public function test_deepseek_cache_read_exceeds_prompt_tokens(): void
    {
        // Quando cacheReadInputTokens > promptTokens:
        // - cacheMissTokens = max(0, 500k - 900k) = 0 (sem cobrança de miss)
        // - O provider cobra o que reportou em cacheReadInputTokens (900k), não limitado a promptTokens
        // miss: 0 tokens * $0.14/M = $0
        // hit:  900_000 * $0.0028/M = $0.00252
        // output: 100_000 * $0.28/M = $0.028
        // total: $0.03052
        $service = $this->makeService();
        $cost = $service->estimateCost('deepseek', 'deepseek-v3', 500_000, 100_000, 900_000);

        $this->assertEqualsWithDelta(0.03052, $cost, 0.000001);
    }

    // ── estimateCost: OpenRouter (gratuito) ──────────────────────────────────

    public function test_openrouter_free_model_costs_zero(): void
    {
        $service = $this->makeService();
        $cost = $service->estimateCost('openrouter', 'z-ai/glm-4.5-air:free', 500_000, 200_000);

        $this->assertSame(0.0, $cost);
    }

    // ── estimateCost: provider desconhecido ──────────────────────────────────

    public function test_unknown_provider_costs_zero(): void
    {
        $service = $this->makeService();
        $cost = $service->estimateCost('provider_inexistente', 'model-x', 999_999, 999_999);

        $this->assertSame(0.0, $cost);
    }

    // ── estimateCost: zero tokens ────────────────────────────────────────────

    public function test_zero_tokens_costs_zero_for_all_providers(): void
    {
        $service = $this->makeService();

        foreach (['anthropic', 'openai', 'deepseek', 'openrouter'] as $provider) {
            $cost = $service->estimateCost($provider, 'model', 0, 0);
            $this->assertSame(0.0, $cost, "Custo não foi zero para provider: {$provider}");
        }
    }

    // ── logRequest: mapeamento de campos e contagem total ────────────────────

    public function test_log_request_total_tokens_is_sum_of_prompt_and_completion(): void
    {
        $capturedData = null;
        $fakeLog = new AiRequestLog;
        $fakeLog->total_tokens = 1500;

        $this->repo->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$capturedData) {
                $capturedData = $data;

                return true;
            })
            ->andReturn($fakeLog);

        $service = $this->makeService();
        $service->logRequest([
            'user_id' => 1,
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-6',
            'prompt_tokens' => 1000,
            'completion_tokens' => 500,
            'duration_ms' => 1200,
            'status' => 'success',
        ]);

        $this->assertIsArray($capturedData);
        // total_tokens deve ser prompt + completion quando não fornecido explicitamente
        $this->assertSame(1500, $capturedData['total_tokens']);
    }

    public function test_log_request_uses_explicit_total_tokens_when_provided(): void
    {
        $capturedData = null;
        $fakeLog = new AiRequestLog;

        $this->repo->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$capturedData) {
                $capturedData = $data;

                return true;
            })
            ->andReturn($fakeLog);

        $service = $this->makeService();
        $service->logRequest([
            'prompt_tokens' => 1000,
            'completion_tokens' => 500,
            'total_tokens' => 9999, // valor explícito — deve prevalecer
        ]);

        $this->assertIsArray($capturedData);
        $this->assertSame(9999, $capturedData['total_tokens']);
    }

    public function test_log_request_auto_computes_cost_when_not_provided(): void
    {
        $capturedData = null;
        $fakeLog = new AiRequestLog;

        $this->repo->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$capturedData) {
                $capturedData = $data;

                return true;
            })
            ->andReturn($fakeLog);

        $service = $this->makeService();
        $service->logRequest([
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-6',
            'prompt_tokens' => 1_000_000,
            'completion_tokens' => 1_000_000,
            // estimated_cost_usd não fornecido → deve ser calculado
        ]);

        $this->assertIsArray($capturedData);
        // $3.00 input + $15.00 output = $18.00
        $this->assertEqualsWithDelta(18.00, (float) $capturedData['estimated_cost_usd'], 0.000001);
    }

    public function test_log_request_uses_explicit_cost_when_provided(): void
    {
        $capturedData = null;
        $fakeLog = new AiRequestLog;

        $this->repo->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$capturedData) {
                $capturedData = $data;

                return true;
            })
            ->andReturn($fakeLog);

        $service = $this->makeService();
        $service->logRequest([
            'provider' => 'anthropic',
            'prompt_tokens' => 1_000_000,
            'completion_tokens' => 1_000_000,
            'estimated_cost_usd' => 0.001, // custo explícito — deve prevalecer
        ]);

        $this->assertIsArray($capturedData);
        $this->assertEqualsWithDelta(0.001, (float) $capturedData['estimated_cost_usd'], 0.0000001);
    }

    public function test_log_request_defaults_missing_fields_to_zero(): void
    {
        $capturedData = null;
        $fakeLog = new AiRequestLog;

        $this->repo->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$capturedData) {
                $capturedData = $data;

                return true;
            })
            ->andReturn($fakeLog);

        $service = $this->makeService();
        $service->logRequest([]); // nenhum campo — tudo default

        $this->assertIsArray($capturedData);
        $this->assertSame(0, $capturedData['prompt_tokens']);
        $this->assertSame(0, $capturedData['completion_tokens']);
        $this->assertSame(0, $capturedData['total_tokens']);
        $this->assertSame(0, $capturedData['duration_ms']);
        $this->assertSame(0, $capturedData['tool_calls_count']);
        $this->assertSame('success', $capturedData['status']);
    }

    public function test_log_request_error_status_is_stored(): void
    {
        $capturedData = null;
        $fakeLog = new AiRequestLog;

        $this->repo->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use (&$capturedData) {
                $capturedData = $data;

                return true;
            })
            ->andReturn($fakeLog);

        $service = $this->makeService();
        $service->logRequest([
            'status' => 'error',
            'error_message' => 'Timeout ao chamar o provider',
        ]);

        $this->assertIsArray($capturedData);
        $this->assertSame('error', $capturedData['status']);
        $this->assertSame('Timeout ao chamar o provider', $capturedData['error_message']);
    }

    public function test_budget_status_marks_exceeded_from_monthly_spend(): void
    {
        $service = new class($this->repo, $this->planMatrix) extends AiTelemetryService
        {
            public function getTenantMonthlyCost(): float
            {
                return 12.345678;
            }
        };

        $status = $service->getBudgetStatus();

        $this->assertSame(10.0, $status['budget_usd']);
        $this->assertSame(12.345678, $status['spent_usd']);
        $this->assertSame(0.0, $status['remaining_usd']);
        $this->assertSame(123.5, $status['usage_percent']);
        $this->assertTrue($status['exceeded']);
    }

    public function test_budget_status_respects_zero_plan_budget(): void
    {
        $tenant = new Tenant;

        $this->planMatrix->shouldReceive('resolveForTenant')
            ->once()
            ->with($tenant)
            ->andReturn(['features' => [], 'limits' => ['ai_budget' => 0]]);

        $this->repo->shouldReceive('getCurrentMonthCost')
            ->once()
            ->andReturn(0.0);

        tenancy()->tenant = $tenant;
        tenancy()->initialized = true;

        try {
            $status = $this->makeService()->getBudgetStatus();
        } finally {
            tenancy()->tenant = null;
            tenancy()->initialized = false;
        }

        $this->assertSame(0.0, $status['budget_usd']);
        $this->assertSame(0.0, $status['spent_usd']);
        $this->assertSame(0.0, $status['remaining_usd']);
        $this->assertSame(100, $status['usage_percent']);
        $this->assertTrue($status['exceeded']);
    }

    // ── getUsageStats: agregação ─────────────────────────────────────────────

    public function test_usage_stats_totals_are_correct(): void
    {
        $logs = new Collection([
            $this->fakeLog(['total_tokens' => 1000, 'estimated_cost_usd' => '0.005000', 'duration_ms' => 300, 'status' => 'success', 'provider' => 'anthropic']),
            $this->fakeLog(['total_tokens' => 2000, 'estimated_cost_usd' => '0.010000', 'duration_ms' => 500, 'status' => 'success', 'provider' => 'anthropic']),
            $this->fakeLog(['total_tokens' => 500,  'estimated_cost_usd' => '0.000000', 'duration_ms' => 100, 'status' => 'error',   'provider' => 'openrouter']),
        ]);

        $this->repo->shouldReceive('getLogsBetween')
            ->once()
            ->andReturn($logs);

        $service = $this->makeService();
        $stats = $service->getUsageStats(Carbon::now()->subDay());

        $this->assertSame(3, $stats['total_requests']);
        $this->assertSame(3500, $stats['total_tokens']);
        $this->assertEqualsWithDelta(0.015, $stats['total_cost'], 0.000001);
        $this->assertSame(1, $stats['error_count']);
        $this->assertEqualsWithDelta(33.33, $stats['error_rate'], 0.01);
    }

    public function test_usage_stats_provider_breakdown_is_correct(): void
    {
        $logs = new Collection([
            $this->fakeLog(['total_tokens' => 1000, 'estimated_cost_usd' => '0.005000', 'duration_ms' => 300, 'status' => 'success', 'provider' => 'anthropic']),
            $this->fakeLog(['total_tokens' => 2000, 'estimated_cost_usd' => '0.010000', 'duration_ms' => 500, 'status' => 'success', 'provider' => 'anthropic']),
            $this->fakeLog(['total_tokens' => 500,  'estimated_cost_usd' => '0.000000', 'duration_ms' => 100, 'status' => 'success', 'provider' => 'openrouter']),
        ]);

        $this->repo->shouldReceive('getLogsBetween')
            ->once()
            ->andReturn($logs);

        $service = $this->makeService();
        $stats = $service->getUsageStats(Carbon::now()->subDay());

        $this->assertArrayHasKey('anthropic', $stats['provider_breakdown']);
        $this->assertArrayHasKey('openrouter', $stats['provider_breakdown']);
        $this->assertSame(2, $stats['provider_breakdown']['anthropic']['requests']);
        $this->assertSame(3000, $stats['provider_breakdown']['anthropic']['tokens']);
        $this->assertEqualsWithDelta(0.015, $stats['provider_breakdown']['anthropic']['cost'], 0.000001);
        $this->assertSame(1, $stats['provider_breakdown']['openrouter']['requests']);
    }

    public function test_usage_stats_empty_logs(): void
    {
        $this->repo->shouldReceive('getLogsBetween')
            ->once()
            ->andReturn(new Collection([]));

        $service = $this->makeService();
        $stats = $service->getUsageStats(Carbon::now()->subDay());

        $this->assertSame(0, $stats['total_requests']);
        $this->assertSame(0, $stats['total_tokens']);
        $this->assertSame(0.0, $stats['total_cost']);
        $this->assertEquals(0, $stats['error_rate']);
        $this->assertSame(0, $stats['p50_duration_ms']);
        $this->assertSame(0, $stats['p95_duration_ms']);
    }

    public function test_usage_stats_p50_p95_duration_are_correct(): void
    {
        // 5 durations ordenadas: 100, 200, 300, 400, 500
        // P50 = índice ceil(50/100 * 5) - 1 = ceil(2.5)-1 = 2 → valor 300
        // P95 = índice ceil(95/100 * 5) - 1 = ceil(4.75)-1 = 4 → valor 500
        $logs = new Collection(array_map(
            fn ($ms) => $this->fakeLog(['total_tokens' => 100, 'estimated_cost_usd' => '0', 'duration_ms' => $ms, 'status' => 'success', 'provider' => 'openrouter']),
            [300, 100, 500, 200, 400] // fora de ordem de propósito
        ));

        $this->repo->shouldReceive('getLogsBetween')
            ->once()
            ->andReturn($logs);

        $service = $this->makeService();
        $stats = $service->getUsageStats(Carbon::now()->subDay());

        $this->assertSame(300, $stats['p50_duration_ms']);
        $this->assertSame(500, $stats['p95_duration_ms']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeService(): AiTelemetryService
    {
        return new AiTelemetryService($this->repo, $this->planMatrix);
    }

    private function mockTelemetryRepository(): AiTelemetryRepositoryInterface&MockInterface
    {
        $mock = Mockery::mock(AiTelemetryRepositoryInterface::class);

        if (! $mock instanceof AiTelemetryRepositoryInterface) {
            throw new \RuntimeException('Mock de telemetria inválido.');
        }

        return $mock;
    }

    private function mockPlanMatrix(): PlanMatrixService&MockInterface
    {
        $mock = Mockery::mock(PlanMatrixService::class);

        if (! $mock instanceof PlanMatrixService) {
            throw new \RuntimeException('Mock da matriz de plano inválido.');
        }

        return $mock;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fakeLog(array $attributes): AiRequestLog
    {
        $log = new AiRequestLog;
        foreach ($attributes as $key => $value) {
            $log->$key = $value;
        }

        return $log;
    }
}
