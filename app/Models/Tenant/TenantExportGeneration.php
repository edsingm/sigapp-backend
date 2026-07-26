<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\TenantExportStatus;
use App\Enums\TenantExportType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $requested_by
 * @property string $idempotency_key
 * @property TenantExportType $type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $filters
 * @property array<string, mixed>|null $payload
 * @property TenantExportStatus $status
 * @property int $progress
 * @property string|null $storage_disk
 * @property string|null $storage_path
 * @property string|null $file_name
 * @property string|null $mime_type
 * @property int|null $size
 * @property string|null $error_message
 * @property Carbon|null $requested_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property-read User|null $requester
 */
#[Table('tenant_export_generations')]
#[Fillable([
    'requested_by',
    'idempotency_key',
    'type',
    'subject_id',
    'filters',
    'payload',
    'status',
    'progress',
    'storage_disk',
    'storage_path',
    'file_name',
    'mime_type',
    'size',
    'error_message',
    'requested_at',
    'started_at',
    'completed_at',
    'expires_at',
])]
class TenantExportGeneration extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $casts = [
        'type' => TenantExportType::class,
        'subject_id' => 'integer',
        'filters' => 'array',
        'payload' => 'array',
        'status' => TenantExportStatus::class,
        'progress' => 'integer',
        'size' => 'integer',
        'requested_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
