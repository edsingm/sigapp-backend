<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string|null $stripe_id
 * @property string|null $stripe_subscription_id
 * @property int|null $plan_id
 * @property \Carbon\Carbon|null $trial_ends_at
 * @property string|null $encryption_key
 * @property bool $database_created
 * @property \Carbon\Carbon|null $setup_completed_at
 * @property bool $trial_extended
 * @property string|null $admin_name
 * @property string|null $admin_email
 * @property string|null $admin_password
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory, \Laravel\Cashier\Billable;

    /**
     * The table associated with the model.
     */
    protected $table = 'tenants';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
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

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'admin_password',
        'encryption_key',
    ];

    /**
     * The attributes that should be cast.
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
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the plan associated with the tenant.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the tenant database/schema identifier.
     */
    public function getDatabaseName(): string
    {
        return static::makeTenantDatabaseIdentifier((string) $this->slug);
    }

    /**
     * Accessor for database name.
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

        $identifier = Str::of(Str::ascii(Str::lower($prefix . $normalizedSlug . $suffix)))
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->value();

        if ($identifier === '' || preg_match('/^[0-9]/', $identifier)) {
            $identifier = 'tenant_' . ltrim($identifier, '0123456789');
            $identifier = rtrim($identifier, '_');
        }

        if (strlen($identifier) <= 63) {
            return $identifier;
        }

        $hash = substr(sha1($identifier), 0, 8);

        return substr($identifier, 0, 54) . '_' . $hash;
    }

    /**
     * Scope for active tenants.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for pending tenants.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for expired pending tenants (more than 2 hours old).
     */
    public function scopeExpiredPending(Builder $query): Builder
    {
        return $query->pending()
            ->where('created_at', '<', now()->subHours(2));
    }

    /**
     * Check if tenant is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if tenant is on trial.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if tenant has exceeded trial.
     */
    public function trialEnded(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Activate the tenant.
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
     * Suspend the tenant.
     */
    public function suspend(): self
    {
        $this->update(['status' => self::STATUS_SUSPENDED]);

        return $this;
    }

    /**
     * Cancel the tenant subscription.
     */
    public function cancel(): self
    {
        $this->update(['status' => self::STATUS_CANCELLED]);

        return $this;
    }

    /**
     * Generate a unique encryption key for the tenant.
     */
    public function generateEncryptionKey(): string
    {
        $key = base64_encode(random_bytes(32));
        $this->update(['encryption_key' => $key]);

        return $key;
    }

    /**
     * Get contractual limits from the assigned plan.
     */
    public function getMaxUsersAttribute(): int
    {
        return $this->plan?->max_users ?? 0;
    }

    public function getMaxTerrenosAttribute(): int
    {
        return $this->plan?->max_terrenos ?? 0;
    }

    public function getMaxStorageGbAttribute(): int
    {
        return $this->plan?->max_storage_gb ?? 0;
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
