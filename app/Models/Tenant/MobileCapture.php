<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $client_id
 * @property int $user_id
 * @property int $version
 * @property string $status
 * @property array<string, mixed>|null $payload
 * @property string|float|null $latitude
 * @property string|float|null $longitude
 * @property string|float|null $accuracy
 * @property Carbon|null $captured_at
 * @property int|null $terreno_id
 * @property array<string, mixed>|null $conflict_details
 * @property Carbon|null $committed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('mobile_captures')]
#[Fillable(['client_id', 'user_id', 'version', 'status', 'payload', 'latitude', 'longitude', 'accuracy', 'captured_at', 'terreno_id', 'conflict_details', 'committed_at'])]
class MobileCapture extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $casts = [
        'payload' => 'array',
        'conflict_details' => 'array',
        'captured_at' => 'datetime',
        'committed_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'accuracy' => 'decimal:2',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Terreno, $this> */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }

    /** @return HasMany<MobileCaptureAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MobileCaptureAttachment::class);
    }
}
