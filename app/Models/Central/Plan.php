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
 * @property array $features
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
        'features',
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
            'features' => 'array',
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
        return 'R$ ' . number_format($this->price, 2, ',', '.');
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

    /**
     * Check if plan has a specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Read an entitlement (dot notation) with legacy fallback derived from features/limits.
     */
    public function getEntitlement(string $key, mixed $default = null): mixed
    {
        $entitlements = $this->entitlements ?? [];

        if (data_get($entitlements, $key, '__missing__') !== '__missing__') {
            return data_get($entitlements, $key, $default);
        }

        $legacy = $this->legacyEntitlements();

        return data_get($legacy, $key, $default);
    }

    public function hasEntitlement(string $key): bool
    {
        return $this->getEntitlement($key, null) !== null;
    }

    /**
     * Get feature flags for the plan.
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
            'full_integrations' => (bool) $this->getEntitlement('integrations.full', false),
            'priority_support' => (bool) $this->getEntitlement('support.priority', false),
        ];
    }

    protected function legacyEntitlements(): array
    {
        $features = $this->features ?? [];

        $has = static fn (string $feature): bool => in_array($feature, $features, true);

        $viabilidadeTier = 'none';
        if ($has('Módulo Viabilidade completo')) {
            $viabilidadeTier = 'full';
        } elseif ($has('Módulo Viabilidade simples') || $has('Módulo Viabilidade bloqueado') === false) {
            // Compatibilidade: planos atuais não usam "bloqueado"; inferimos simples.
            $viabilidadeTier = 'simple';
        }

        $reportsTier = $has('Relatórios avançados') ? 'advanced' : 'basic';

        return [
            'users' => [
                'max' => $this->max_users,
            ],
            'terrenos' => [
                'max' => $this->max_terrenos,
            ],
            'storage' => [
                'max_gb' => $this->max_storage_gb,
            ],
            'viabilidade' => [
                'enabled' => $viabilidadeTier !== 'none',
                'tier' => $viabilidadeTier,
            ],
            'reports' => [
                'tier' => $reportsTier,
            ],
            'api_access' => [
                'enabled' => $has('API Access') || $has('Integração via API própria'),
            ],
            'sso' => [
                'enabled' => $has('SSO via SAML'),
            ],
            'integrations' => [
                'full' => $has('Integrações completas') || $has('Integração via API própria'),
            ],
            'support' => [
                'priority' => $has('Suporte prioritário'),
            ],
        ];
    }
}
