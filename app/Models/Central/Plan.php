<?php

namespace App\Models\Central;

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
 * @property int $max_users
 * @property int $max_storage_gb
 * @property int $max_terrenos
 * @property bool $is_active
 * @property bool $is_popular
 * @property int $sort_order
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
        'max_users',
        'max_storage_gb',
        'max_terrenos',
        'entitlements',
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
            'max_users' => 'integer',
            'max_storage_gb' => 'integer',
            'max_terrenos' => 'integer',
            'entitlements' => 'array',
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
        return $this->max_users === -1;
    }

    /**
     * Check if plan has unlimited terrenos.
     */
    public function hasUnlimitedTerrenos(): bool
    {
        return $this->max_terrenos === -1;
    }

    public function getEntitlement(string $key, mixed $default = null): mixed
    {
        return data_get($this->entitlements ?? [], $key, $default);
    }

    public function hasEntitlement(string $key): bool
    {
        return $this->getEntitlement($key, null) !== null;
    }

    /**
     * Get derived plan flags from the canonical entitlements catalog.
     */
    public function getFeatureFlagsAttribute(): array
    {
        $viabilityTier = (string) ($this->getEntitlement('viabilidade.tier', 'none') ?? 'none');

        return [
            'viability_enabled' => (bool) $this->getEntitlement('viabilidade.enabled', $viabilityTier !== 'none'),
            'viability_full' => in_array($viabilityTier, ['full', 'advanced', 'pro', 'enterprise'], true),
            'api_access' => (bool) $this->getEntitlement('api_access.enabled', false),
            'sso_enabled' => (bool) $this->getEntitlement('sso.enabled', false),
            'advanced_reports' => in_array((string) $this->getEntitlement('reports.tier', 'basic'), ['advanced', 'full', 'pro', 'enterprise'], true),
            'export_pdf' => (bool) $this->getEntitlement('reports.export_pdf', false),
            'dashboard_full' => in_array((string) $this->getEntitlement('dashboard.tier', 'basic'), ['advanced', 'full', 'pro', 'enterprise'], true),
            'dre_full' => in_array((string) $this->getEntitlement('dre.tier', 'none'), ['advanced', 'full', 'pro', 'enterprise'], true),
            'cash_flow' => (bool) $this->getEntitlement('cash_flow.enabled', false),
            'kpis_indicators' => (bool) $this->getEntitlement('analytics.kpis.enabled', false)
                || (bool) $this->getEntitlement('analytics.indicators.enabled', false),
            'full_integrations' => (bool) $this->getEntitlement('integrations.full', false),
            'priority_support' => (bool) $this->getEntitlement('support.priority', false),
            'custom_roles' => (bool) $this->getEntitlement('acl.custom_roles.enabled', false),
            'permission_management' => (bool) $this->getEntitlement('acl.permission_management.enabled', false),
        ];
    }

}
