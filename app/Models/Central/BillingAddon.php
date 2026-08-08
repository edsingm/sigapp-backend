<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Common\BillingAddonType;
use Carbon\Carbon;
use Database\Factories\Central\BillingAddonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property BillingAddonType $type
 * @property string|null $stripe_price_id
 * @property string $currency
 * @property string $billing_interval
 * @property array{grants: list<array{key: string, type: string, unit_value: bool|int|float}>} $definition
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Table('billing_addons')]
#[Fillable([
    'slug',
    'name',
    'description',
    'type',
    'stripe_price_id',
    'currency',
    'billing_interval',
    'definition',
    'is_active',
    'sort_order',
])]
class BillingAddon extends Model
{
    /** @use HasFactory<BillingAddonFactory> */
    use CentralConnection, HasFactory;

    /** @return Factory<BillingAddon> */
    protected static function newFactory(): Factory
    {
        return BillingAddonFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => BillingAddonType::class,
            'definition' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return HasMany<TenantAddonSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantAddonSubscription::class);
    }

    /** @return HasMany<TenantAddonPurchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(TenantAddonPurchase::class);
    }

    public function isPurchasable(): bool
    {
        return $this->is_active
            && is_string($this->stripe_price_id)
            && $this->stripe_price_id !== '';
    }
}
