<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable(['user_id', 'action', 'description', 'ip_address', 'user_agent', 'metadata'])]
/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $description
 * @property string|null $entity_type
 * @property string|int|null $entity_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon|null $created_at
 * @property-read User|null $user
 */
class AuditLog extends Model
{
    use CentralConnection, HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the user that performed the action.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
