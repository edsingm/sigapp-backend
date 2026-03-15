<?php

namespace App\Models\Central;

use App\Services\PlanMatrixService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $stripe_price_id
 * @property int $price
 * @property int $trial_days
 * @property bool $is_active
 * @property bool $is_popular
 * @property int $sort_order
 * @property array<string, mixed> $features
 * @property array<string, int> $limits
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Plan extends Model
{
    use HasFactory, CentralConnection;

    /**
     * The table associated with the model.
     */
    protected $table = 'plans';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'stripe_price_id',
        'price',
        'trial_days',
        'is_active',
        'is_popular',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the tenants for the plan.
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Scope for active plans.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordering by sort_order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Get the formatted price in BRL.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'R$ ' . number_format($this->price / 100, 2, ',', '.');
    }

    /**
     * Check if plan has unlimited users.
     */
    public function hasUnlimitedUsers(): bool
    {
        return $this->getLimit('users') === -1;
    }

    /**
     * Check if plan has unlimited terrenos.
     */
    public function hasUnlimitedTerrenos(): bool
    {
        return $this->getLimit('terrenos') === -1;
    }

    public function getFeaturesAttribute(): array
    {
        return app(PlanMatrixService::class)->features($this);
    }

    public function getLimitsAttribute(): array
    {
        return app(PlanMatrixService::class)->limits($this);
    }

    public function getFeature(string $key, mixed $default = null): mixed
    {
        return app(PlanMatrixService::class)->featureValue($this, $key, $default);
    }

    public function hasFeature(string $key): bool
    {
        return app(PlanMatrixService::class)->hasFeature($this, $key);
    }

    public function getLimit(string $key, int $default = 0): int
    {
        return app(PlanMatrixService::class)->getLimit($this, $key, $default);
    }

    public function hasUnlimitedLimit(string $key): bool
    {
        return $this->getLimit($key) === -1;
    }
}
