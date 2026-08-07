<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Common\BillingAddonSubscriptionStatus;
use App\Models\Central\BillingAddon;
use App\Models\Central\TenantAddonSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @phpstan-extends Factory<TenantAddonSubscription> */
class TenantAddonSubscriptionFactory extends Factory
{
    protected $model = TenantAddonSubscription::class;

    public function definition(): array
    {
        return [
            'tenant_id' => (string) Str::uuid(),
            'billing_addon_id' => BillingAddon::factory(),
            'stripe_subscription_id' => 'sub_'.fake()->unique()->regexify('[a-zA-Z0-9]{16}'),
            'stripe_subscription_item_id' => 'si_'.fake()->unique()->regexify('[a-zA-Z0-9]{16}'),
            'stripe_price_id' => 'price_'.fake()->unique()->regexify('[a-zA-Z0-9]{16}'),
            'quantity' => 1,
            'status' => BillingAddonSubscriptionStatus::ACTIVE,
            'cancel_at_period_end' => false,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
            'canceled_at' => null,
            'last_synced_at' => now(),
        ];
    }
}
