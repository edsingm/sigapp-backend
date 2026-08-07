<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Common\BillingAddonSubscriptionStatus;
use Carbon\Carbon;
use Database\Factories\Central\TenantAddonSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $billing_addon_id
 * @property string $stripe_subscription_id
 * @property string $stripe_subscription_item_id
 * @property string $stripe_price_id
 * @property int $quantity
 * @property BillingAddonSubscriptionStatus $status
 * @property bool $cancel_at_period_end
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $canceled_at
 * @property Carbon|null $last_synced_at
 * @property-read BillingAddon|null $addon
 * @property-read Tenant|null $tenant
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Table('tenant_addon_subscriptions')]
#[Fillable([
    'tenant_id',
    'billing_addon_id',
    'stripe_subscription_id',
    'stripe_subscription_item_id',
    'stripe_price_id',
    'quantity',
    'status',
    'cancel_at_period_end',
    'current_period_start',
    'current_period_end',
    'canceled_at',
    'last_synced_at',
])]
class TenantAddonSubscription extends Model
{
    /** @use HasFactory<TenantAddonSubscriptionFactory> */
    use CentralConnection, HasFactory;

    /** @return Factory<TenantAddonSubscription> */
    protected static function newFactory(): Factory
    {
        return TenantAddonSubscriptionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => BillingAddonSubscriptionStatus::class,
            'cancel_at_period_end' => 'boolean',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<BillingAddon, $this> */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(BillingAddon::class, 'billing_addon_id');
    }

    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess() && $this->quantity > 0;
    }
}
