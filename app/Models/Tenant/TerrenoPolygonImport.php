<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\TerrenoPolygonImportStatus;
use Carbon\Carbon;
use Database\Factories\Tenant\TerrenoPolygonImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $requested_by
 * @property string $idempotency_key
 * @property TerrenoPolygonImportStatus $status
 * @property int $progress
 * @property int $total_files
 * @property int $processed_files
 * @property int $failed_files
 * @property int $polygon_count
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $requested_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property-read User $requester
 * @property-read Collection<int, TerrenoPolygonImportFile> $files
 */
#[Table('terreno_polygon_imports')]
#[Fillable(['requested_by', 'idempotency_key', 'status', 'progress', 'total_files', 'processed_files', 'failed_files', 'polygon_count', 'error_code', 'error_message', 'requested_at', 'started_at', 'completed_at'])]
class TerrenoPolygonImport extends Model
{
    /** @use HasFactory<TerrenoPolygonImportFactory> */
    use HasFactory;

    protected $casts = [
        'status' => TerrenoPolygonImportStatus::class,
        'progress' => 'integer',
        'total_files' => 'integer',
        'processed_files' => 'integer',
        'failed_files' => 'integer',
        'polygon_count' => 'integer',
        'requested_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return HasMany<TerrenoPolygonImportFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(TerrenoPolygonImportFile::class);
    }

    /** @return HasMany<TerrenoPendingPolygon, $this> */
    public function polygons(): HasMany
    {
        return $this->hasMany(TerrenoPendingPolygon::class);
    }
}
