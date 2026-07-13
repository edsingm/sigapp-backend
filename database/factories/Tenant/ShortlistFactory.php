<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Shortlist;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<Shortlist>
 */
class ShortlistFactory extends Factory
{
    protected $model = Shortlist::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'scope' => 'private',
            'is_default' => false,
        ];
    }
}
