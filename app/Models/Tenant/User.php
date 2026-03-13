<?php

namespace App\Models\Tenant;

use App\Enums\Common\RolesEnum;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Notifications\TenantResetPasswordNotification;
use App\Services\Auth\TenantPasswordResetService;
use App\Services\Auth\TenantUserDirectoryService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\HasDashboardCache;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, HasDashboardCache;

    private const ADMIN_ROLE_NAMES = [RolesEnum::ADMIN->value];

    protected $guard_name = 'web';

    protected static function booted()
    {
        static::saved(function (User $model) {
            $model->clearTenantCache('users');
            $model->clearTenantCache('terrenos');
            $model->clearTenantCache('legalizacoes');

            if (tenancy()->initialized) {
                app(TenantUserDirectoryService::class)->syncUser($model);
            }
        });

        static::deleted(function (User $model) {
            $model->clearTenantCache('users');
            $model->clearTenantCache('terrenos');
            $model->clearTenantCache('legalizacoes');

            if (tenancy()->initialized) {
                app(TenantUserDirectoryService::class)->deleteUser($model);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'locale' => 'string',
        ];
    }

    /**
     * Check if user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RolesEnum::ADMIN->value);
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(self::ADMIN_ROLE_NAMES);
    }

    /**
     * Get the plan for the current tenant
     *
     * @return Plan|null
     */
    public function getPlan(): ?Plan
    {
        $tenant = tenancy()->tenant;
        if (!$tenant) {
            return null;
        }

        // Executa a query no banco central
        return tenancy()->central(function () use ($tenant) {
            $centralTenant = Tenant::with('plan')->find($tenant->id);
            return $centralTenant?->plan;
        });
    }

    public function sendPasswordResetNotification($token): void
    {
        $tenant = tenant();

        if (!$tenant instanceof Tenant) {
            return;
        }

        $resetUrl = app(TenantPasswordResetService::class)->buildResetUrl(
            $tenant,
            (string) $token,
            (string) $this->email,
        );

        $this->notify(new TenantResetPasswordNotification(
            $resetUrl,
            (int) config('auth.passwords.tenant_users.expire', 60),
        ));
    }
}
