<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\AiRequestLog;
use App\Repositories\Contracts\AiTelemetryRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AiTelemetryService
{
    /**
     * Preços por 1M tokens (USD). Configurável via env.
     */
    protected array $priceMap = [];

    public function __construct(
        private readonly AiTelemetryRepositoryInterface $repository
    ) {
        $this->priceMap = [
            'openrouter' => [
                'input' => (float) env('AI_OPENROUTER_INPUT_PRICE_PER_M', 0.00),
                'output' => (float) env('AI_OPENROUTER_OUTPUT_PRICE_PER_M', 0.00),
            ],
            'anthropic' => [
                'input' => (float) env('AI_ANTHROPIC_INPUT_PRICE_PER_M', 3.00),
                'output' => (float) env('AI_ANTHROPIC_OUTPUT_PRICE_PER_M', 15.00),
            ],
            'openai' => [
                'input' => (float) env('AI_OPENAI_INPUT_PRICE_PER_M', 2.50),
                'output' => (float) env('AI_OPENAI_OUTPUT_PRICE_PER_M', 10.00),
            ],
        ];
    }

    /**
     * Registra um log de requisição de IA.
     */
    public function logRequest(array $data): AiRequestLog
    {
        return $this->repository->create([
            'user_id' => $data['user_id'] ?? null,
            'conversation_id' => $data['conversation_id'] ?? null,
            'provider' => $data['provider'] ?? null,
            'model' => $data['model'] ?? null,
            'prompt_tokens' => $data['prompt_tokens'] ?? 0,
            'completion_tokens' => $data['completion_tokens'] ?? 0,
            'total_tokens' => $data['total_tokens'] ?? ($data['prompt_tokens'] ?? 0) + ($data['completion_tokens'] ?? 0),
            'estimated_cost_usd' => $data['estimated_cost_usd'] ?? $this->estimateCost(
                $data['provider'] ?? null,
                $data['model'] ?? null,
                $data['prompt_tokens'] ?? 0,
                $data['completion_tokens'] ?? 0,
            ),
            'duration_ms' => $data['duration_ms'] ?? 0,
            'tool_calls_count' => $data['tool_calls_count'] ?? 0,
            'tool_calls' => $data['tool_calls'] ?? null,
            'status' => $data['status'] ?? 'success',
            'error_message' => $data['error_message'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
        ]);
    }

    /**
     * Estima custo baseado em provider/modelo e tokens.
     */
    public function estimateCost(?string $provider, ?string $model, int $promptTokens, int $completionTokens): float
    {
        $prices = $this->priceMap[$provider] ?? ['input' => 0, 'output' => 0];

        $inputCost = ($promptTokens / 1_000_000) * $prices['input'];
        $outputCost = ($completionTokens / 1_000_000) * $prices['output'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Custo acumulado por usuário no período.
     */
    public function getCostByUser(int $userId, Carbon $from, ?Carbon $to = null): float
    {
        return $this->repository->getCostByUser($userId, $from, $to);
    }

    /**
     * Custo acumulado do tenant no período (mês corrente).
     */
    public function getTenantMonthlyCost(): float
    {
        if (! tenancy()->initialized) {
            return 0;
        }

        return $this->repository->getCurrentMonthCost();
    }

    /**
     * Estatísticas de uso no período.
     */
    public function getUsageStats(Carbon $from, ?Carbon $to = null): array
    {
        $logs = $this->repository->getLogsBetween($from, $to);

        $durations = $logs->pluck('duration_ms')->sort()->values();
        $tokens = $logs->pluck('total_tokens')->sort()->values();
        $costs = $logs->pluck('estimated_cost_usd');

        return [
            'total_requests' => $logs->count(),
            'total_tokens' => $logs->sum('total_tokens'),
            'total_cost' => round($costs->sum(), 6),
            'avg_cost' => round($costs->avg() ?? 0, 6),
            'avg_duration_ms' => round($durations->avg() ?? 0, 0),
            'p50_duration_ms' => $this->percentile($durations, 50),
            'p95_duration_ms' => $this->percentile($durations, 95),
            'error_count' => $logs->where('status', '!=', 'success')->count(),
            'error_rate' => $logs->count() > 0
                ? round(($logs->where('status', '!=', 'success')->count() / $logs->count()) * 100, 2)
                : 0,
            'provider_breakdown' => $this->groupByProvider($logs),
        ];
    }

    /**
     * Group usage by provider.
     */
    protected function groupByProvider(Collection $logs): array
    {
        return $logs->groupBy('provider')
            ->map(fn ($group) => [
                'requests' => $group->count(),
                'tokens' => $group->sum('total_tokens'),
                'cost' => round($group->sum('estimated_cost_usd'), 6),
            ])
            ->toArray();
    }

    /**
     * Calcula percentil de uma coleção ordenada.
     */
    protected function percentile(Collection $values, int $percentile): int
    {
        if ($values->isEmpty()) {
            return 0;
        }

        $index = (int) ceil(($percentile / 100) * $values->count()) - 1;

        return (int) ($values[max(0, $index)] ?? 0);
    }

    /**
     * Verifica se o tenant excedeu o orçamento mensal.
     */
    public function hasExceededBudget(float $budgetLimit): bool
    {
        return $this->getTenantMonthlyCost() >= $budgetLimit;
    }

    /**
     * Retorna o orçamento atual e o gasto.
     */
    public function getBudgetStatus(): array
    {
        $budgetLimit = (float) env('AI_TENANT_BUDGET_DEFAULT', 10.00);
        $spent = $this->getTenantMonthlyCost();

        return [
            'budget_usd' => $budgetLimit,
            'spent_usd' => round($spent, 6),
            'remaining_usd' => round(max(0, $budgetLimit - $spent), 6),
            'usage_percent' => $budgetLimit > 0 ? round(($spent / $budgetLimit) * 100, 1) : 100,
            'exceeded' => $spent >= $budgetLimit,
        ];
    }
}
