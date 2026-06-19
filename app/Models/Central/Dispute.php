<?php

namespace App\Models\Central;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'stripe_dispute_id', 'stripe_charge_id', 'amount', 'reason', 'status', 'resolved_at'])]
/**
 * @property int $id
 * @property string $tenant_id
 * @property string $stripe_dispute_id
 * @property string $stripe_charge_id
 * @property int $amount
 * @property string|null $reason
 * @property string $status
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant|null $tenant
 */
class Dispute extends Model
{
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
