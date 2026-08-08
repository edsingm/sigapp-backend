<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Central\BillingAddon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

class BillingAddonPricingService
{
    private const CACHE_TTL_MINUTES = 5;

    public function __construct(
        private readonly TenantBillingService $billingService,
    ) {}

    /**
     * @return array{
     *     unit_amount: int|null,
     *     currency: string,
     *     interval: string,
     *     price_type: string,
     *     formatted_price: string|null,
     *     is_purchasable: bool
     * }
     */
    public function details(BillingAddon $addon): array
    {
        $fallback = $this->unavailable($addon);
        $priceId = $addon->stripe_price_id;

        if (! $addon->is_active || ! is_string($priceId) || $priceId === '') {
            return $fallback;
        }

        return Cache::remember(
            "billing-addon-price:{$priceId}",
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($addon, $fallback, $priceId): array {
                try {
                    $price = $this->billingService->retrievePrice($priceId);
                    $unitAmount = data_get($price, 'unit_amount');
                    $currency = data_get($price, 'currency');
                    $priceType = data_get($price, 'type');
                    if ($priceType === null && data_get($price, 'recurring.interval') !== null) {
                        $priceType = 'recurring';
                    }
                    $interval = $priceType === 'one_time'
                        ? 'one_time'
                        : data_get($price, 'recurring.interval');
                    $isActive = data_get($price, 'active') === true;

                    if (
                        ! $isActive
                        || ! is_int($unitAmount)
                        || $unitAmount < 0
                        || ! is_string($currency)
                        || $currency === ''
                        || ! in_array($priceType, ['one_time', 'recurring'], true)
                        || ($priceType === 'recurring' && $interval !== 'month')
                    ) {
                        return $fallback;
                    }

                    $currency = strtolower($currency);

                    return [
                        'unit_amount' => $unitAmount,
                        'currency' => $currency,
                        'interval' => (string) $interval,
                        'price_type' => $priceType,
                        'formatted_price' => $this->format($unitAmount, $currency, $priceType),
                        'is_purchasable' => true,
                    ];
                } catch (\Throwable $exception) {
                    Log::warning('Não foi possível consultar o preço do add-on no Stripe.', [
                        'addon_slug' => $addon->slug,
                        'stripe_price_id' => $priceId,
                        'error' => $exception->getMessage(),
                    ]);

                    return $fallback;
                }
            },
        );
    }

    public function hydrate(BillingAddon $addon): BillingAddon
    {
        $addon->setAttribute('price_details', $this->details($addon));

        return $addon;
    }

    /**
     * @return array{
     *     unit_amount: int|null,
     *     currency: string,
     *     interval: string,
     *     price_type: string,
     *     formatted_price: string|null,
     *     is_purchasable: bool
     * }
     */
    private function unavailable(BillingAddon $addon): array
    {
        return [
            'unit_amount' => null,
            'currency' => strtolower($addon->currency),
            'interval' => $addon->billing_interval,
            'price_type' => $addon->billing_interval === 'one_time' ? 'one_time' : 'recurring',
            'formatted_price' => null,
            'is_purchasable' => false,
        ];
    }

    private function format(int $unitAmount, string $currency, string $priceType): string
    {
        return Cashier::formatAmount(
            $unitAmount,
            $currency,
        ).($priceType === 'recurring' ? '/mês' : '');
    }
}
