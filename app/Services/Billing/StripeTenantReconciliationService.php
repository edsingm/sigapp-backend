<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Jobs\CreateFullTenantJob;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Log;

class StripeTenantReconciliationService
{
    public function __construct(
        private readonly TenantBillingService $billing,
        private readonly AddonReconciliationService $addons,
    ) {}

    /** @param array<string, mixed> $context */
    public function reconcile(
        Tenant $tenant,
        string $subscriptionId,
        string $source,
        array $context = [],
        bool $skipPlanSync = false,
    ): void {
        if ($subscriptionId === '') {
            throw new \RuntimeException('Evento Stripe sem subscription_id para reconciliação.');
        }

        $stripeSubscription = $this->billing->retrieveSubscription($subscriptionId);
        $stripeStatus = (string) ($stripeSubscription->status ?? '');
        if ($stripeStatus === '') {
            throw new \RuntimeException('Assinatura Stripe sem status para reconciliação.');
        }

        $tenant->update([
            'stripe_id' => $stripeSubscription->customer ?? $tenant->getAttribute('stripe_id'),
            'stripe_subscription_id' => $stripeSubscription->id ?? $subscriptionId,
        ]);

        if (! $skipPlanSync) {
            $this->billing->syncPlanFromSubscription($tenant, $stripeSubscription);
        }

        $this->billing->syncSubscription($tenant, $subscriptionId);
        $this->addons->reconcile($tenant, $stripeSubscription);
        $appliedStatus = $this->billing->applyStripeSubscriptionStatus($tenant, $stripeStatus);

        Log::info('Tenant reconciliado a partir do Stripe', array_merge([
            'tenant_id' => $tenant->getKey(),
            'source' => $source,
            'stripe_status' => $stripeStatus,
            'applied_status' => $appliedStatus,
        ], $context));

        if (in_array($stripeStatus, ['active', 'trialing'], true)
            && ! (bool) $tenant->getAttribute('database_created')) {
            CreateFullTenantJob::dispatch($tenant)->onQueue('tenant-provisioning');
        }
    }
}
