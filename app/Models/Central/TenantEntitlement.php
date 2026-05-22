<?php

namespace App\Models\Central;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $entitlement_id
 * @property mixed $value
 * @property int $price Custo mensal adicional em centavos
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Table('tenant_entitlements')]
#[Fillable(['tenant_id', 'entitlement_id', 'value', 'price'])]
class TenantEntitlement extends Model
{
    use CentralConnection, HasFactory;

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'price' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(Entitlement::class);
    }
}
