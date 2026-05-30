<?php

namespace App\Models\Central;

use App\Enums\TenantStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

#[Table('tenants')]
#[Fillable(['name', 'slug', 'status', 'stripe_id', 'stripe_subscription_id', 'plan_id', 'trial_ends_at', 'encryption_key', 'database_created', 'setup_completed_at', 'trial_extended', 'admin_name', 'admin_email', 'admin_password', 'data'])]
#[Hidden(['admin_password', 'encryption_key'])]
/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string|null $stripe_id
 * @property string|null $stripe_subscription_id
 * @property int|null $plan_id
 * @property Carbon|null $trial_ends_at
 * @property string|null $encryption_key
 * @property bool $database_created
 * @property Carbon|null $setup_completed_at
 * @property bool $trial_extended
 * @property string|null $admin_name
 * @property string|null $admin_email
 * @property string|null $admin_password
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory, \Laravel\Cashier\Billable, Notifiable;

    /**
     * Os atributos que devem ser convertidos.
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'setup_completed_at' => 'datetime',
            'database_created' => 'boolean',
            'trial_extended' => 'boolean',
            'data' => 'array',
            'admin_password' => 'hashed',
        ];
    }

    /**
     * Constantes de status (alias do Enum TenantStatus).
     */
    public const STATUS_PENDING = TenantStatus::PENDING->value;

    public const STATUS_ACTIVE = TenantStatus::ACTIVE->value;

    public const STATUS_SUSPENDED = TenantStatus::SUSPENDED->value;

    public const STATUS_CANCELLED = TenantStatus::CANCELLED->value;

    public const STATUS_SETUP_FAILED = TenantStatus::SETUP_FAILED->value;

    /**
     * Obtém o plano associado ao tenant.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Obtém os entitlements extras contratados por este tenant.
     */
    public function extraEntitlements(): HasMany
    {
        return $this->hasMany(TenantEntitlement::class, 'tenant_id');
    }

    /**
     * Soma do custo mensal adicional dos entitlements extras em centavos.
     */
    public function getExtraMonthlyCostAttribute(): int
    {
        return (int) $this->extraEntitlements()->sum('price');
    }

    /**
     * Obtém o identificador do banco de dados/schema do tenant.
     */
    public function getDatabaseName(): string
    {
        return static::makeTenantDatabaseIdentifier((string) $this->getAttribute('slug'));
    }

    /**
     * Accessor para o nome do banco de dados.
     */
    public function getDatabaseNameAttribute(): string
    {
        return $this->getDatabaseName();
    }

    public static function makeTenantDatabaseIdentifier(string $slug): string
    {
        $prefix = (string) config('tenancy.database.prefix', 'tenant_');
        $suffix = (string) config('tenancy.database.suffix', '');

        $normalizedSlug = Str::of(Str::ascii(Str::lower($slug)))
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->value();

        if ($normalizedSlug === '') {
            $normalizedSlug = 'tenant';
        }

        $identifier = Str::of(Str::ascii(Str::lower($prefix.$normalizedSlug.$suffix)))
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->value();

        if ($identifier === '' || preg_match('/^[0-9]/', $identifier)) {
            $identifier = 'tenant_'.ltrim($identifier, '0123456789');
            $identifier = rtrim($identifier, '_');
        }

        if (strlen($identifier) <= 63) {
            return $identifier;
        }

        $hash = substr(sha1($identifier), 0, 8);

        return substr($identifier, 0, 54).'_'.$hash;
    }

    /**
     * Escopo para tenants ativos.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Escopo para tenants pendentes.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Escopo para tenants pendentes expirados (com mais de 24 horas).
     */
    public function scopeExpiredPending(Builder $query): Builder
    {
        return $query->pending()
            ->where('created_at', '<', now()->subDay());
    }

    /**
     * Verifica se o tenant está ativo.
     */
    public function isActive(): bool
    {
        return (string) $this->getAttribute('status') === self::STATUS_ACTIVE;
    }

    /**
     * Verifica se o tenant está em período de teste.
     */
    public function onTrial(): bool
    {
        $trialEndsAt = $this->getAttribute('trial_ends_at');

        return $trialEndsAt instanceof Carbon && $trialEndsAt->isFuture();
    }

    /**
     * Verifica se o tenant excedeu o período de teste.
     */
    public function trialEnded(): bool
    {
        $trialEndsAt = $this->getAttribute('trial_ends_at');

        return $trialEndsAt instanceof Carbon && $trialEndsAt->isPast();
    }

    /**
     * Ativa o tenant.
     */
    public function activate(): self
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'setup_completed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Suspende o tenant.
     */
    public function suspend(): self
    {
        $this->update(['status' => self::STATUS_SUSPENDED]);

        return $this;
    }

    /**
     * Cancela a assinatura do tenant.
     */
    public function cancel(): self
    {
        $this->update(['status' => self::STATUS_CANCELLED]);

        return $this;
    }

    /**
     * Gera uma chave de criptografia exclusiva para o tenant.
     */
    public function generateEncryptionKey(): string
    {
        $key = base64_encode(random_bytes(32));
        $this->update(['encryption_key' => $key]);

        return $key;
    }

    /**
     * Cache local dos limites resolvidos para evitar múltiplas resoluções da matrix.
     *
     * @var array<string, int>|null
     */
    private ?array $resolvedLimits = null;

    /**
     * Obtém os limites do plano com cache local por instância.
     *
     * @return array<string, int>
     */
    private function getResolvedLimits(): array
    {
        if ($this->resolvedLimits === null) {
            $this->resolvedLimits = $this->plan?->getLimitsAttribute() ?? [];
        }

        return $this->resolvedLimits;
    }

    /**
     * Obtém um limite específico do plano.
     */
    private function getPlanLimit(string $key, int $default = 0): int
    {
        $value = data_get($this->getResolvedLimits(), $key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Obtém os limites contratuais do plano atribuído.
     */
    public function getMaxUsersAttribute(): int
    {
        return $this->getPlanLimit('users');
    }

    public function getMaxTerrenosAttribute(): int
    {
        return $this->getPlanLimit('terrenos');
    }

    public function getMaxStorageGbAttribute(): int
    {
        return $this->getPlanLimit('storage_gb');
    }

    public function getMaxProductsAttribute(): int
    {
        return $this->getPlanLimit('products');
    }

    /**
     * Roteia as notificações para o endereço de e-mail do administrador.
     */
    public function routeNotificationForMail(): ?string
    {
        $adminEmail = $this->getAttribute('admin_email');

        return is_string($adminEmail) && $adminEmail !== '' ? $adminEmail : null;
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'created_at',
            'updated_at',
            'name',
            'slug',
            'status',
            'stripe_id',
            'stripe_subscription_id',
            'plan_id',
            'trial_ends_at',
            'encryption_key',
            'database_created',
            'setup_completed_at',
            'trial_extended',
            'admin_name',
            'admin_email',
            'admin_password',
            'data',
        ];
    }
}
