<?php

declare(strict_types=1);

namespace App\Services\Ai\Tools;

use App\Exceptions\AiBudgetExceededException;
use App\Models\Central\Tenant;
use App\Models\Tenant\AiRequestLog;
use App\Repositories\Contracts\AiTelemetryRepositoryInterface;
use App\Services\Billing\AiCreditService;
use App\Services\PlanMatrixService as ServicesPlanMatrixService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AiTelemetryService
{
    /**
     * Preços por 1M tokens (USD). Configurável via env.
     *
     * @var array<string, array<string, float>>
     */
    protected array $priceMap = [];

    public function __construct(
        private readonly AiTelemetryRepositoryInterface $repository,
        private readonly ServicesPlanMatrixService $planMatrix,
        private readonly AiCreditService $aiCredits,
    ) {
        /** @var array<string, array<string, float>> $priceMap */
        $priceMap = config('ai.prices_per_million_tokens', []);
        $this->priceMap = $priceMap;
    }

    /**
     * Registra um log de requisição de IA.
     *
     * @param  array<string, mixed>  $data
     */
    public function logRequest(array $data): AiRequestLog
    {
        if (! tenancy()->initialized) {
            return $this->repository->create($this->requestPayload($data));
        }

        return Cache::lock($this->budgetLockKey(), 10)->block(5, function () use ($data): AiRequestLog {
            $log = $this->repository->create($this->requestPayload($data));
            $this->syncCreditsToCurrentCommitment(allowOverdraft: true);

            return $log;
        });
    }

    /**
     * Telemetria best effort para nunca interromper a operação principal.
     *
     * @param  array<string, mixed>  $data
     */
    public function tryLogRequest(array $data): ?AiRequestLog
    {
        try {
            return $this->logRequest($data);
        } catch (Throwable $exception) {
            $this->reportTelemetryFailure('log', $exception);

            return null;
        }
    }

    /**
     * Reserva parte do orçamento antes de chamar o provider.
     *
     * @param  array<string, mixed>  $data
     */
    public function reserveBudget(array $data, float $amount): AiRequestLog
    {
        if (! tenancy()->initialized) {
            throw new RuntimeException('Não é possível reservar orçamento de IA fora do contexto tenant.');
        }

        $amount = round(max(0.000001, $amount), 6);

        return Cache::lock($this->budgetLockKey(), 10)->block(5, function () use ($data, $amount): AiRequestLog {
            $this->repository->expireStaleReservations(
                now()->subMinutes((int) config('ai.budget_reservation_ttl_minutes', 15))
            );

            $budgetLimit = $this->resolveBudgetLimit();
            $committed = $this->repository->getCurrentMonthCost();
            $tenant = $this->currentTenant();

            if (! $tenant instanceof Tenant || $budgetLimit <= 0) {
                throw new AiBudgetExceededException;
            }

            $this->syncCreditsToCommitment($tenant, $budgetLimit, $committed, allowOverdraft: true);
            $credits = $this->aiCredits->summary($tenant);
            $effectiveLimit = $budgetLimit
                + $credits['consumed_this_month_usd']
                + $credits['balance_usd'];

            if ($committed + $amount > $effectiveLimit) {
                throw new AiBudgetExceededException;
            }

            $reservation = $this->repository->create($this->requestPayload([
                ...$data,
                'estimated_cost_usd' => $amount,
                'status' => 'reserved',
            ]));

            try {
                $this->syncCreditsToCommitment(
                    $tenant,
                    $budgetLimit,
                    $committed + $amount,
                    allowOverdraft: false,
                );
            } catch (Throwable $exception) {
                $this->repository->update($reservation, [
                    'estimated_cost_usd' => 0,
                    'status' => 'expired',
                    'error_message' => 'Reserva de crédito não confirmada.',
                ]);

                throw $exception;
            }

            return $reservation;
        });
    }

    /**
     * Substitui a reserva pelo custo real e pelos dados finais da chamada.
     *
     * @param  array<string, mixed>  $data
     */
    public function settleReservation(AiRequestLog $reservation, array $data): AiRequestLog
    {
        return Cache::lock($this->budgetLockKey(), 10)->block(
            5,
            function () use ($reservation, $data): AiRequestLog {
                $settled = $this->repository->update(
                    $reservation,
                    $this->requestPayload([...$data, 'status' => $data['status'] ?? 'success'])
                );
                $this->syncCreditsToCurrentCommitment(allowOverdraft: true);

                return $settled;
            }
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function trySettleReservation(AiRequestLog $reservation, array $data): ?AiRequestLog
    {
        try {
            return $this->settleReservation($reservation, $data);
        } catch (Throwable $exception) {
            $this->reportTelemetryFailure('settle', $exception);

            return null;
        }
    }

    /**
     * Libera o valor reservado quando a chamada não chega a consumir o provider.
     *
     * @param  array<string, mixed>  $data
     */
    public function failReservation(AiRequestLog $reservation, array $data): AiRequestLog
    {
        return $this->settleReservation($reservation, [
            ...$data,
            'estimated_cost_usd' => $data['estimated_cost_usd'] ?? 0,
            'status' => $data['status'] ?? 'error',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function tryFailReservation(AiRequestLog $reservation, array $data): ?AiRequestLog
    {
        try {
            return $this->failReservation($reservation, $data);
        } catch (Throwable $exception) {
            $this->reportTelemetryFailure('fail', $exception);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function requestPayload(array $data): array
    {
        return [
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
        ];
    }

    /**
     * Estima custo baseado em provider/modelo e tokens.
     *
     * $cacheReadInputTokens: tokens servidos do cache do provider (cache hit).
     * Para DeepSeek, promptTokens = total (hit + miss); cacheMiss = promptTokens - cacheReadInputTokens.
     * Para provedores sem cache diferenciado, cacheReadInputTokens = 0 e o input é cobrado pela
     * chave 'input' do priceMap.
     */
    public function estimateCost(
        ?string $provider,
        ?string $model,
        int $promptTokens,
        int $completionTokens,
        int $cacheReadInputTokens = 0,
    ): float {
        $prices = $this->priceMap[$provider] ?? ['input' => 0, 'output' => 0];

        if (isset($prices['input_cache_miss'])) {
            $cacheMissTokens = max(0, $promptTokens - $cacheReadInputTokens);
            $cacheHitPrice = $prices['input_cache_hit'] ?? $prices['input_cache_miss'];
            $inputCost = ($cacheMissTokens / 1_000_000) * $prices['input_cache_miss']
                       + ($cacheReadInputTokens / 1_000_000) * $cacheHitPrice;
        } else {
            $inputCost = ($promptTokens / 1_000_000) * ($prices['input'] ?? 0);
        }

        $outputCost = ($completionTokens / 1_000_000) * ($prices['output'] ?? 0);

        return round($inputCost + $outputCost, 6);
    }

    public function estimateEmbeddingCost(?string $provider, int $tokens): float
    {
        $price = (float) config("ai.embedding_prices_per_million_tokens.{$provider}", 0);

        return round(($tokens / 1_000_000) * $price, 6);
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
     *
     * @return array<string, mixed>
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
            'tools' => AiToolCallTelemetry::summarizeFromLogs($logs),
        ];
    }

    /**
     * Estatísticas agregadas de tools no período (atalho).
     *
     * @return array<string, mixed>
     */
    public function getToolUsageStats(Carbon $from, ?Carbon $to = null): array
    {
        $logs = $this->repository->getLogsBetween($from, $to);

        return AiToolCallTelemetry::summarizeFromLogs($logs);
    }

    /**
     * Group usage by provider.
     *
     * @param  Collection<int, AiRequestLog>  $logs
     * @return array<string, array<string, int|float>>
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
     *
     * @param  Collection<int, mixed>  $values
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

    public function ensureBudgetAvailable(): void
    {
        $budget = $this->getBudgetStatus();

        if ($budget['exceeded']) {
            throw new AiBudgetExceededException;
        }
    }

    /**
     * Resolve o limite de orçamento do tenant: plano > env default.
     */
    private function resolveBudgetLimit(): float
    {
        $default = (float) config('ai.tenant_budget_default', 10.00);

        try {
            if (! tenancy()->initialized) {
                return $default;
            }

            $tenant = tenancy()->tenant;
            $limits = $this->planMatrix->resolveForTenant($tenant)['limits'];

            if (array_key_exists('ai_budget', $limits)) {
                return (float) $limits['ai_budget'];
            }
        } catch (Throwable) {
            // Fallback ao default se o plano não estiver configurado
        }

        return $default;
    }

    /**
     * Retorna o orçamento atual e o gasto.
     *
     * @return array<string, int|float|bool>
     */
    public function getBudgetStatus(): array
    {
        $budgetLimit = $this->resolveBudgetLimit();
        $spent = $this->getTenantMonthlyCost();
        $tenant = $this->currentTenant();
        $credits = $tenant instanceof Tenant
            ? $this->aiCredits->summary($tenant)
            : [
                'balance_usd' => 0.0,
                'purchased_usd' => 0.0,
                'consumed_usd' => 0.0,
                'consumed_this_month_usd' => 0.0,
            ];
        $effectiveLimit = $budgetLimit > 0
            ? $budgetLimit + $credits['consumed_this_month_usd'] + $credits['balance_usd']
            : 0.0;

        return [
            'budget_usd' => round($effectiveLimit, 6),
            'plan_budget_usd' => round($budgetLimit, 6),
            'addon_credit_balance_usd' => $credits['balance_usd'],
            'addon_credit_consumed_usd' => $credits['consumed_usd'],
            'spent_usd' => round($spent, 6),
            'remaining_usd' => round(max(0, $effectiveLimit - $spent), 6),
            'usage_percent' => $effectiveLimit > 0 ? round(($spent / $effectiveLimit) * 100, 1) : 100,
            'exceeded' => $spent >= $effectiveLimit,
        ];
    }

    private function syncCreditsToCurrentCommitment(bool $allowOverdraft): void
    {
        $tenant = $this->currentTenant();
        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->syncCreditsToCommitment(
            $tenant,
            $this->resolveBudgetLimit(),
            $this->repository->getCurrentMonthCost(),
            $allowOverdraft,
        );
    }

    private function syncCreditsToCommitment(
        Tenant $tenant,
        float $planBudget,
        float $committed,
        bool $allowOverdraft,
    ): void {
        if ($planBudget <= 0) {
            return;
        }

        $requiredCredits = round(max(0, $committed - $planBudget), 6);
        $currentConsumption = $this->aiCredits->summary($tenant)['consumed_this_month_usd'];

        if (abs($requiredCredits - $currentConsumption) < 0.000001) {
            return;
        }

        $this->aiCredits->syncMonthlyConsumption($tenant, $requiredCredits, $allowOverdraft);
    }

    private function currentTenant(): ?Tenant
    {
        $tenant = tenancy()->tenant;

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function budgetLockKey(): string
    {
        return sprintf(
            'ai-budget:%s:%s',
            (string) tenant('id'),
            now()->format('Y-m'),
        );
    }

    private function reportTelemetryFailure(string $operation, Throwable $exception): void
    {
        Log::warning('AI telemetry persistence failed', [
            'operation' => $operation,
            'tenant_id' => tenancy()->initialized ? tenant('id') : null,
            'error' => $exception->getMessage(),
        ]);
    }
}
