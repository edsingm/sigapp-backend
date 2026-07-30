<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\TerrenoImportStatus;
use Carbon\Carbon;
use Database\Factories\Tenant\TerrenoImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $requested_by
 * @property string $idempotency_key
 * @property TerrenoImportStatus $status
 * @property int $progress
 * @property string|null $storage_disk
 * @property string|null $storage_path
 * @property string $file_name
 * @property string|null $mime_type
 * @property int|null $size
 * @property string|null $checksum
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $invalid_rows
 * @property int $imported_rows
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $requested_at
 * @property Carbon|null $started_at
 * @property Carbon|null $validated_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property-read User $requester
 */
#[Table('terreno_imports')]
#[Fillable(['requested_by', 'idempotency_key', 'status', 'progress', 'storage_disk', 'storage_path', 'file_name', 'mime_type', 'size', 'checksum', 'total_rows', 'valid_rows', 'invalid_rows', 'imported_rows', 'error_code', 'error_message', 'requested_at', 'started_at', 'validated_at', 'confirmed_at', 'completed_at', 'expires_at'])]
class TerrenoImport extends Model
{
    /** @use HasFactory<TerrenoImportFactory> */
    use HasFactory;

    protected $casts = [
        'status' => TerrenoImportStatus::class,
        'progress' => 'integer',
        'size' => 'integer',
        'total_rows' => 'integer',
        'valid_rows' => 'integer',
        'invalid_rows' => 'integer',
        'imported_rows' => 'integer',
        'requested_at' => 'datetime',
        'started_at' => 'datetime',
        'validated_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return HasMany<TerrenoImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(TerrenoImportRow::class);
    }
}
