<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Common\BillingAddonType;
use App\Models\Central\BillingAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<BillingAddon> */
class BillingAddonFactory extends Factory
{
    protected $model = BillingAddon::class;

    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'slug' => $slug,
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'type' => BillingAddonType::LIMIT_PACK,
            'stripe_price_id' => 'price_'.fake()->unique()->regexify('[a-zA-Z0-9]{16}'),
            'currency' => 'brl',
            'billing_interval' => 'month',
            'definition' => [
                'grants' => [
                    [
                        'key' => 'storage_gb',
                        'type' => 'limit',
                        'unit_value' => 10,
                    ],
                ],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
