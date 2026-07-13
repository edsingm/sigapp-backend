<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Shortlist;
use App\Models\Tenant\ShortlistItem;
use App\Models\Tenant\Terreno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @phpstan-extends Factory<ShortlistItem>
 */
class ShortlistItemFactory extends Factory
{
    protected $model = ShortlistItem::class;

    public function definition(): array
    {
        return [
            'shortlist_id' => Shortlist::factory(),
            'terreno_id' => Terreno::factory(),
            'position' => 0,
        ];
    }
}
