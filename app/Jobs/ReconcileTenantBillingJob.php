<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Central\Tenant;
use App\Services\Billing\StripeTenantReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(5)]
#[Backoff([60, 300, 900, 1800])]
#[Timeout(180)]
#[Queue('default')]
class ReconcileTenantBillingJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public function __construct(public readonly string $tenantId) {}

    public function uniqueId(): string
    {
        return 'stripe-reconcile:'.$this->tenantId;
    }

    public function handle(StripeTenantReconciliationService $reconciliation): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant instanceof Tenant || $tenant->getAttribute('wiped_at') !== null) {
            return;
        }

        $subscriptionId = (string) $tenant->getAttribute('stripe_subscription_id');
        if ($subscriptionId === '') {
            return;
        }

        $reconciliation->reconcile($tenant, $subscriptionId, 'scheduled-reconciliation');
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('Reconciliação Stripe esgotou as tentativas.', [
            'tenant_id' => $this->tenantId,
            'exception' => $exception::class,
        ]);
    }
}
