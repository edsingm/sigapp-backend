<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Central\Tenant;
use App\Models\Central\TenantAddonSubscription;
use App\Repositories\Contracts\TenantAddonSubscriptionRepositoryInterface;
use App\Services\PlanMatrixService;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class TenantAddonAdminService
{
    public function __construct(
        private readonly TenantAddonSubscriptionRepositoryInterface $subscriptionRepository,
        private readonly TenantBillingService $billingService,
        private readonly AddonReconciliationService $reconciliationService,
        private readonly PlanMatrixService $planMatrixService,
    ) {}

    /** @return Collection<int, TenantAddonSubscription> */
    public function subscriptions(Tenant $tenant): Collection
    {
        return $this->subscriptionRepository->forTenant($tenant);
    }

    /**
     * Busca o Stripe como fonte da verdade e atualiza os registros locais.
     *
     * @return array{matched: int, canceled: int, ignored: int}
     */
    public function reconcile(Tenant $tenant): array
    {
        $subscriptionId = $tenant->getAttribute('stripe_subscription_id');
        if (! is_string($subscriptionId) || $subscriptionId === '') {
            throw new InvalidArgumentException('Tenant não possui assinatura Stripe vinculada.');
        }

        $stripeSubscription = $this->billingService->retrieveSubscription($subscriptionId);

        return $this->reconciliationService->reconcile($tenant, $stripeSubscription);
    }

    /** @return array<string, mixed> */
    public function accessMatrix(Tenant $tenant): array
    {
        $tenant->loadMissing('plan');

        return [
            'tenant' => [
                'id' => $tenant->getKey(),
                'slug' => $tenant->getAttribute('slug'),
                'name' => $tenant->getAttribute('name'),
            ],
            'plan' => $tenant->plan ? [
                'id' => $tenant->plan->getKey(),
                'slug' => $tenant->plan->getAttribute('slug'),
                'name' => $tenant->plan->getAttribute('name'),
            ] : null,
            'matrix' => $this->planMatrixService->resolveForTenant($tenant),
            'addons' => $this->subscriptions($tenant)->map(
                static fn (TenantAddonSubscription $subscription): array => [
                    'id' => $subscription->getKey(),
                    'slug' => $subscription->addon?->slug,
                    'quantity' => $subscription->quantity,
                    'status' => $subscription->status->value,
                    'grants_access' => $subscription->grantsAccess(),
                ],
            )->values()->all(),
        ];
    }
}
