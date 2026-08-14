<?php

declare(strict_types=1);

namespace App\Models\Central;

use Carbon\Carbon;
use Database\Factories\Central\LegalAcceptanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $actor_email
 * @property string $document_key
 * @property string $document_version
 * @property string $document_hash
 * @property Carbon $accepted_at
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'actor_email',
    'document_key',
    'document_version',
    'document_hash',
    'accepted_at',
    'ip_hash',
    'user_agent',
])]
class LegalAcceptance extends Model
{
    /** @use HasFactory<LegalAcceptanceFactory> */
    use CentralConnection, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
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
