<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonPurchase;
use App\Repositories\Contracts\AiCreditTransactionRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class AiCreditService
{
    public function __construct(
        private readonly AiCreditTransactionRepositoryInterface $repository,
    ) {}

    public function grantFromPurchase(TenantAddonPurchase $purchase): float
    {
        $amount = $this->aiBudgetGrant($purchase->grant_snapshot) * $purchase->quantity;
        if ($amount <= 0) {
            return 0;
        }

        $this->repository->creditPurchase(
            $purchase,
            $amount,
            'addon-purchase:'.$purchase->getKey(),
        );

        return round($amount, 6);
    }

    /**
     * Mantém uma única entrada de consumo por mês. A entrada pode diminuir quando
     * uma reserva expira ou liquida abaixo da estimativa, devolvendo o excedente ao saldo.
     */
    public function syncMonthlyConsumption(
        Tenant $tenant,
        float $amount,
        bool $allowOverdraft = false,
    ): float {
        $month = now()->format('Y-m');

        return Cache::lock('tenant-ai-credit:'.$tenant->getKey(), 10)
            ->block(5, function () use ($tenant, $amount, $allowOverdraft, $month): float {
                $amount = round(max(0, $amount), 6);
                $currentMonthConsumption = $this->repository->monthConsumption($tenant, $month);
                $availableBeforeMonthConsumption = round(
                    $this->repository->balance($tenant) + $currentMonthConsumption,
                    6,
                );

                if (! $allowOverdraft && $amount > $availableBeforeMonthConsumption) {
                    throw new InvalidArgumentException('Saldo de créditos adicionais de IA insuficiente.');
                }

                $this->repository->syncMonthConsumption($tenant, $month, $amount);

                return $amount;
            });
    }

    /** @return array{balance_usd: float, purchased_usd: float, consumed_usd: float, consumed_this_month_usd: float} */
    public function summary(Tenant $tenant): array
    {
        return [
            'balance_usd' => round(max(0, $this->repository->balance($tenant)), 6),
            'purchased_usd' => $this->repository->totalCredited($tenant),
            'consumed_usd' => $this->repository->totalConsumed($tenant),
            'consumed_this_month_usd' => $this->repository->monthConsumption($tenant, now()->format('Y-m')),
        ];
    }

    /** @param array<string, mixed> $definition */
    public function aiBudgetGrant(array $definition): float
    {
        $amount = 0.0;

        foreach ((array) ($definition['grants'] ?? []) as $grant) {
            if (! is_array($grant) || ($grant['key'] ?? null) !== 'ai_budget') {
                continue;
            }

            $unitValue = $grant['unit_value'] ?? null;
            if (is_numeric($unitValue)) {
                $amount += (float) $unitValue;
            }
        }

        return round(max(0, $amount), 6);
    }
}
