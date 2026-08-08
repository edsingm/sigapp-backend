<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Common\AiCreditTransactionType;
use App\Models\Central\AiCreditTransaction;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonPurchase;
use App\Repositories\Contracts\AiCreditTransactionRepositoryInterface;

class AiCreditTransactionRepository implements AiCreditTransactionRepositoryInterface
{
    public function balance(Tenant $tenant): float
    {
        return round((float) AiCreditTransaction::query()
            ->where('tenant_id', $tenant->getKey())
            ->sum('amount_usd'), 6);
    }

    public function totalCredited(Tenant $tenant): float
    {
        return round((float) AiCreditTransaction::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('type', AiCreditTransactionType::CREDIT->value)
            ->sum('amount_usd'), 6);
    }

    public function totalConsumed(Tenant $tenant): float
    {
        return round(abs((float) AiCreditTransaction::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('type', AiCreditTransactionType::CONSUMPTION->value)
            ->sum('amount_usd')), 6);
    }

    public function monthConsumption(Tenant $tenant, string $month): float
    {
        $amount = AiCreditTransaction::query()
            ->where('reference', $this->monthReference($tenant, $month))
            ->value('amount_usd');

        return round(abs((float) ($amount ?? 0)), 6);
    }

    public function creditPurchase(
        TenantAddonPurchase $purchase,
        float $amount,
        string $reference,
    ): AiCreditTransaction {
        return AiCreditTransaction::query()->firstOrCreate(
            ['reference' => $reference],
            [
                'tenant_id' => $purchase->tenant_id,
                'tenant_addon_purchase_id' => $purchase->getKey(),
                'type' => AiCreditTransactionType::CREDIT,
                'amount_usd' => round($amount, 6),
                'metadata' => ['billing_addon_id' => $purchase->billing_addon_id],
            ],
        );
    }

    public function syncMonthConsumption(Tenant $tenant, string $month, float $amount): AiCreditTransaction
    {
        return AiCreditTransaction::query()->updateOrCreate(
            ['reference' => $this->monthReference($tenant, $month)],
            [
                'tenant_id' => (string) $tenant->getKey(),
                'tenant_addon_purchase_id' => null,
                'type' => AiCreditTransactionType::CONSUMPTION,
                'amount_usd' => -round(max(0, $amount), 6),
                'metadata' => ['month' => $month],
            ],
        );
    }

    private function monthReference(Tenant $tenant, string $month): string
    {
        return sprintf('ai-budget:%s:%s', (string) $tenant->getKey(), $month);
    }
}
