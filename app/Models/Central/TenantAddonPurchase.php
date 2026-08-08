<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Common\TenantAddonPurchaseStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $billing_addon_id
 * @property string|null $stripe_checkout_session_id
 * @property string|null $stripe_payment_intent_id
 * @property string $stripe_price_id
 * @property string|null $checkout_url
 * @property int $quantity
 * @property int $unit_amount
 * @property int|null $amount_total
 * @property string $currency
 * @property TenantAddonPurchaseStatus $status
 * @property array<string, mixed> $grant_snapshot
 * @property Carbon|null $paid_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $expires_at
 * @property-read BillingAddon|null $addon
 * @property-read Tenant|null $tenant
 */
#[Table('tenant_addon_purchases')]
#[Fillable([
    'tenant_id',
    'billing_addon_id',
    'stripe_checkout_session_id',
    'stripe_payment_intent_id',
    'stripe_price_id',
    'checkout_url',
    'quantity',
    'unit_amount',
    'amount_total',
    'currency',
    'status',
    'grant_snapshot',
    'paid_at',
    'failed_at',
    'expires_at',
])]
class TenantAddonPurchase extends Model
{
    use CentralConnection;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'integer',
            'amount_total' => 'integer',
            'status' => TenantAddonPurchaseStatus::class,
            'grant_snapshot' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
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
}
