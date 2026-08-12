<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\HiperdadosImportStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property HiperdadosImportStatus $status
 * @property int|null $created_by
 * @property string $portal_username
 * @property string|null $credentials_encrypted
 * @property string|null $tenant_id
 * @property int|null $limit_count
 * @property int $total_count
 * @property int $processed_count
 * @property int $failed_count
 * @property int $imported_count
 * @property string|null $storage_disk
 * @property string|null $storage_path
 * @property string|null $error_message
 * @property array<string, mixed>|null $summary
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Tenant|null $tenant
 */
#[Fillable([
    'uuid',
    'status',
    'created_by',
    'portal_username',
    'credentials_encrypted',
    'tenant_id',
    'limit_count',
    'total_count',
    'processed_count',
    'failed_count',
    'imported_count',
    'storage_disk',
    'storage_path',
    'error_message',
    'summary',
    'started_at',
    'finished_at',
])]
class HiperdadosImport extends Model
{
    use CentralConnection;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => HiperdadosImportStatus::class,
            'summary' => 'array',
            'limit_count' => 'integer',
            'total_count' => 'integer',
            'processed_count' => 'integer',
            'failed_count' => 'integer',
            'imported_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
