<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Terreno;
use App\Http\Resources\PlanResource;
use App\Http\Resources\TenantResource;
use App\Services\ApiResponseService;
use App\Services\Billing\TenantBillingService;
use App\Services\UsageMetricsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class TenantController extends Controller
{
    public function __construct(
        protected UsageMetricsService $usageService,
        protected TenantBillingService $billingService
    ) {
    }

    /**
     * Get current tenant info.
     *
     * GET /api/v1/tenant
     */
    public function show()
    {
        Gate::authorize('viewAny', Terreno::class);

        $tenant = tenancy()->tenant;
        $tenant->load('plan');

        return ApiResponseService::success(
            new TenantResource($tenant),
            language()->t('TENANT_DATA_RETRIEVED')
        );
    }

    /**
     * Get tenant usage metrics.
     *
     * GET /api/v1/tenant/usage
     */
    public function usage()
    {
        Gate::authorize('viewAny', Terreno::class);

        return ApiResponseService::success([
            'metrics' => $this->usageService->getMetrics(),
            'percentages' => $this->usageService->getUsagePercentages(),
            'approaching_limits' => $this->usageService->isApproachingLimits(),
        ], language()->t('USAGE_METRICS_RETRIEVED'));
    }

    /**
     * Get subscription status.
     *
     * GET /api/v1/tenant/subscription
     */
    public function subscription()
    {
        Gate::authorize('viewAny', Terreno::class);

        $tenant = tenancy()->tenant;
        $tenant->load('plan');
        $localSubscription = $tenant->subscription('default');

        $stripeData = null;
        $invoices = [];
        $stripeError = null;

        if ($tenant->stripe_id) {
            try {
                $stripe = $tenant->stripe();
                $customer = $stripe->customers->retrieve($tenant->stripe_id, []);

                $stripeSubscription = null;
                if ($tenant->stripe_subscription_id) {
                    $stripeSubscription = $stripe->subscriptions->retrieve($tenant->stripe_subscription_id, []);
                }

                $defaultPaymentMethod = null;
                $defaultPaymentMethodId =
                    $stripeSubscription->default_payment_method
                    ?? ($customer->invoice_settings->default_payment_method ?? null);

                if ($defaultPaymentMethodId) {
                    $defaultPaymentMethod = $stripe->paymentMethods->retrieve($defaultPaymentMethodId, []);
                }

                $stripeData = [
                    'customer' => [
                        'id' => $customer->id ?? null,
                        'email' => $customer->email ?? null,
                        'name' => $customer->name ?? null,
                        'invoice_prefix' => $customer->invoice_prefix ?? null,
                        'default_payment_method' => $defaultPaymentMethod ? [
                            'id' => $defaultPaymentMethod->id ?? null,
                            'brand' => $defaultPaymentMethod->card->brand ?? null,
                            'last4' => $defaultPaymentMethod->card->last4 ?? null,
                            'exp_month' => $defaultPaymentMethod->card->exp_month ?? null,
                            'exp_year' => $defaultPaymentMethod->card->exp_year ?? null,
                        ] : null,
                    ],
                    'subscription' => $stripeSubscription ? [
                        'id' => $stripeSubscription->id ?? null,
                        'status' => $stripeSubscription->status ?? null,
                        'collection_method' => $stripeSubscription->collection_method ?? null,
                        'current_period_start' => $stripeSubscription->current_period_start
                            ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start)->toIso8601String()
                            : null,
                        'current_period_end' => $stripeSubscription->current_period_end
                            ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)->toIso8601String()
                            : null,
                        'cancel_at' => $stripeSubscription->cancel_at
                            ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->cancel_at)->toIso8601String()
                            : null,
                        'cancel_at_period_end' => (bool) ($stripeSubscription->cancel_at_period_end ?? false),
                        'billing_cycle_anchor' => $stripeSubscription->billing_cycle_anchor
                            ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->billing_cycle_anchor)->toIso8601String()
                            : null,
                        'price_id' => $stripeSubscription->items->data[0]->price->id ?? null,
                        'latest_invoice' => $stripeSubscription->latest_invoice ?? null,
                    ] : null,
                ];

                $stripeInvoices = $stripe->invoices->all([
                    'customer' => $tenant->stripe_id,
                    'limit' => 8,
                ]);

                foreach ($stripeInvoices->data ?? [] as $invoice) {
                    $invoices[] = [
                        'id' => $invoice->id ?? null,
                        'number' => $invoice->number ?? null,
                        'status' => $invoice->status ?? null,
                        'amount_due' => $invoice->amount_due ?? null,
                        'amount_paid' => $invoice->amount_paid ?? null,
                        'amount_remaining' => $invoice->amount_remaining ?? null,
                        'currency' => $invoice->currency ?? null,
                        'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                        'invoice_pdf' => $invoice->invoice_pdf ?? null,
                        'created_at' => $invoice->created
                            ? \Carbon\Carbon::createFromTimestamp($invoice->created)->toIso8601String()
                            : null,
                        'period_start' => $invoice->period_start
                            ? \Carbon\Carbon::createFromTimestamp($invoice->period_start)->toIso8601String()
                            : null,
                        'period_end' => $invoice->period_end
                            ? \Carbon\Carbon::createFromTimestamp($invoice->period_end)->toIso8601String()
                            : null,
                    ];
                }
            } catch (\Exception $e) {
                $stripeError = $e->getMessage();
                Log::warning('Erro ao consultar Stripe no billing', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ApiResponseService::success([
            'status' => $tenant->status,
            'plan' => $tenant->plan ? new PlanResource($tenant->plan) : null,
            'on_trial' => $tenant->onTrial(),
            'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
            'trial_ended' => $tenant->trialEnded(),
            'stripe_customer_id' => $tenant->stripe_id,
            'stripe_subscription_id' => $tenant->stripe_subscription_id,
            'local_subscription' => $localSubscription ? [
                'stripe_status' => $localSubscription->stripe_status,
                'trial_ends_at' => $localSubscription->trial_ends_at?->toIso8601String(),
                'ends_at' => $localSubscription->ends_at?->toIso8601String(),
            ] : null,
            'stripe' => $stripeData,
            'invoices' => $invoices,
            'stripe_error' => app()->environment('local') ? $stripeError : null,
        ], language()->t('SIGNATURE_DATA_RETRIEVED'));
    }

    public function billingPortal()
    {
        Gate::authorize('viewAny', Terreno::class);

        $tenant = tenancy()->tenant;

        if (!$tenant->stripe_id) {
            return ApiResponseService::conflict('BILLING_PORTAL_UNAVAILABLE');
        }

        try {
            $returnUrl = rtrim((string) config('app.frontend_url'), '/') . '/billing';

            return ApiResponseService::success([
                'url' => $this->billingService->createBillingPortalUrl($tenant, $returnUrl),
            ], 'SUCCESS_OPERATION');
        } catch (\Exception $e) {
            Log::warning('Erro ao criar billing portal', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponseService::error(
                'BILLING_PORTAL_ERROR',
                'UNKNOWN_ERROR',
                app()->environment('local') ? $e->getMessage() : null,
                500
            );
        }
    }
}
