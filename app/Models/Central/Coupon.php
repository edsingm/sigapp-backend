<?php

namespace App\Models\Central;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string|null $stripe_coupon_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property int|null $amount_off
 * @property int|null $percent_off
 * @property string|null $currency
 * @property int|null $max_redemptions
 * @property int $times_redeemed
 * @property Carbon|null $redeem_by
 * @property bool $expires_after_first_redemption
 * @property bool $is_active
 * @property array<int, int|string>|null $applies_to_plans
 * @property array<int, int|string>|null $applies_to_tenants
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'stripe_coupon_id',
    'code',
    'name',
    'description',
    'type',
    'amount_off',
    'percent_off',
    'currency',
    'max_redemptions',
    'times_redeemed',
    'redeem_by',
    'expires_after_first_redemption',
    'is_active',
    'applies_to_plans',
    'applies_to_tenants',
])]
class Coupon extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'amount_off' => 'integer',
            'percent_off' => 'integer',
            'max_redemptions' => 'integer',
            'times_redeemed' => 'integer',
            'redeem_by' => 'datetime',
            'expires_after_first_redemption' => 'boolean',
            'is_active' => 'boolean',
            'applies_to_plans' => 'array',
            'applies_to_tenants' => 'array',
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
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereNull('redeem_by')
                    ->orWhere('redeem_by', '>', now());
            })
            ->where(function (Builder $q): void {
                $q->whereNull('max_redemptions')
                    ->orWhereColumn('times_redeemed', '<', 'max_redemptions');
            });
    }

    public function isExpired(): bool
    {
        return $this->redeem_by !== null && $this->redeem_by->isPast();
    }

    public function isFullyRedeemed(): bool
    {
        return $this->max_redemptions !== null
            && $this->times_redeemed >= $this->max_redemptions;
    }

    public function isAvailable(): bool
    {
        return $this->is_active
            && ! $this->isExpired()
            && ! $this->isFullyRedeemed();
    }

    public function appliesToPlan(Plan $plan): bool
    {
        if ($this->applies_to_plans === null) {
            return true;
        }

        return in_array($plan->id, $this->applies_to_plans, true)
            || in_array($plan->slug, $this->applies_to_plans, true);
    }

    public function appliesToTenant(Tenant $tenant): bool
    {
        if ($this->applies_to_tenants === null) {
            return true;
        }

        return in_array($tenant->id, $this->applies_to_tenants, true)
            || in_array((string) $tenant->getAttribute('slug'), $this->applies_to_tenants, true);
    }

    public function formattedDiscount(): string
    {
        return match ($this->type) {
            'percent' => "{$this->percent_off}%",
            'fixed' => 'R$ '.number_format($this->amount_off / 100, 2, ',', '.'),
            default => $this->code,
        };
    }
}
