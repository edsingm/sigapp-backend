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

/**
 * @property int $id
 * @property int $report_template_id
 * @property int|null $report_schedule_id
 * @property int $requested_by
 * @property int|null $completed_by
 * @property string $idempotency_key
 * @property array<string, mixed> $definition_snapshot
 * @property array<string, mixed>|null $filters
 * @property string $format
 * @property string $status
 * @property int $progress
 * @property string|null $storage_disk
 * @property string|null $storage_path
 * @property string|null $mime_type
 * @property int|null $size
 * @property string|null $error_message
 * @property Carbon|null $requested_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property-read ReportTemplate|null $template
 * @property-read ReportSchedule|null $schedule
 * @property-read User|null $requester
 */
#[Table('report_runs')]
#[Fillable(['report_template_id', 'report_schedule_id', 'requested_by', 'completed_by', 'idempotency_key', 'definition_snapshot', 'filters', 'format', 'status', 'progress', 'storage_disk', 'storage_path', 'mime_type', 'size', 'error_message', 'requested_at', 'completed_at', 'expires_at'])]
class ReportRun extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $casts = [
        'definition_snapshot' => 'array',
        'filters' => 'array',
        'progress' => 'integer',
        'size' => 'integer',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<ReportTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    /** @return BelongsTo<ReportSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ReportSchedule::class, 'report_schedule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
