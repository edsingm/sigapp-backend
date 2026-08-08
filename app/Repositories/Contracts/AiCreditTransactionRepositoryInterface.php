<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Central\AiCreditTransaction;
use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonPurchase;

interface AiCreditTransactionRepositoryInterface
{
    public function balance(Tenant $tenant): float;

    public function totalCredited(Tenant $tenant): float;

    public function totalConsumed(Tenant $tenant): float;

    public function monthConsumption(Tenant $tenant, string $month): float;

    public function creditPurchase(
        TenantAddonPurchase $purchase,
        float $amount,
        string $reference,
    ): AiCreditTransaction;

    public function syncMonthConsumption(
        Tenant $tenant,
        string $month,
        float $amount,
    ): AiCreditTransaction;
}
