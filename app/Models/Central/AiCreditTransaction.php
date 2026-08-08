<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Common\AiCreditTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int|null $tenant_addon_purchase_id
 * @property AiCreditTransactionType $type
 * @property numeric-string|float $amount_usd
 * @property string $reference
 * @property array<string, mixed>|null $metadata
 */
#[Table('ai_credit_transactions')]
#[Fillable([
    'tenant_id',
    'tenant_addon_purchase_id',
    'type',
    'amount_usd',
    'reference',
    'metadata',
])]
class AiCreditTransaction extends Model
{
    use CentralConnection;

    protected function casts(): array
    {
        return [
            'type' => AiCreditTransactionType::class,
            'amount_usd' => 'decimal:6',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<TenantAddonPurchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(TenantAddonPurchase::class, 'tenant_addon_purchase_id');
    }
}
