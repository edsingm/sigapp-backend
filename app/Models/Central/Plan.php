<?php

namespace App\Models\Central;

use App\Services\PlanMatrixService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
     * A tabela associada ao modelo.
     */
    protected $table = 'plans';

    /**
     * Os atributos que podem ser atribuídos em massa.
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
     * Os atributos que devem ser convertidos.
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
     * Obtém os tenants do plano.
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Obtém os entitlements do plano.
     */
    public function entitlements(): BelongsToMany
    {
        return $this->belongsToMany(Entitlement::class, 'plan_entitlements')
            ->withPivot('value')
            ->withTimestamps();
    }

    /**
     * Escopo para planos ativos.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Escopo para ordenação por sort_order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Obtém o preço formatado em BRL.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'R$ ' . number_format($this->price / 100, 2, ',', '.');
    }

    /**
     * Verifica se o plano tem usuários ilimitados.
     */
    public function hasUnlimitedUsers(): bool
    {
        return $this->getLimit('users') === -1;
    }

    /**
     * Verifica se o plano tem terrenos ilimitados.
     */
    public function hasUnlimitedTerrenos(): bool
    {
        return $this->getLimit('terrenos') === -1;
    }

    /**
     * Obtém as funcionalidades do plano.
     */
    public function getFeaturesAttribute(): array
    {
        return app(PlanMatrixService::class)->features($this);
    }

    /**
     * Obtém os limites do plano.
     */
    public function getLimitsAttribute(): array
    {
        return app(PlanMatrixService::class)->limits($this);
    }

    /**
     * Obtém o valor de uma funcionalidade específica.
     */
    public function getFeature(string $key, mixed $default = null): mixed
    {
        return app(PlanMatrixService::class)->featureValue($this, $key, $default);
    }

    /**
     * Verifica se o plano possui uma funcionalidade específica.
     */
    public function hasFeature(string $key): bool
    {
        return app(PlanMatrixService::class)->hasFeature($this, $key);
    }

    /**
     * Obtém o limite de uma chave específica.
     */
    public function getLimit(string $key, int $default = 0): int
    {
        return app(PlanMatrixService::class)->getLimit($this, $key, $default);
    }

    /**
     * Verifica se o limite de uma chave específica é ilimitado.
     */
    public function hasUnlimitedLimit(string $key): bool
    {
        return $this->getLimit($key) === -1;
    }
}
