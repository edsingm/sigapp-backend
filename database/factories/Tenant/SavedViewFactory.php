<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\SavedView;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @phpstan-extends Factory<SavedView> */
class SavedViewFactory extends Factory
{
    protected $model = SavedView::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'resource' => 'workspace',
            'scope' => 'private',
            'filters' => [],
            'columns' => [],
            'sort' => [],
            'view_mode' => 'list',
            'is_default' => false,
        ];
    }
}
