<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $report_template_id
 * @property int $owner_id
 * @property string $name
 * @property string $frequency
 * @property string $format
 * @property array<string, mixed>|null $filters
 * @property bool $notify_email
 * @property bool $is_active
 * @property Carbon|null $next_run_at
 * @property Carbon|null $last_run_at
 * @property int|null $last_run_id
 * @property-read ReportTemplate|null $template
 * @property-read User|null $owner
 * @property-read ReportRun|null $lastRun
 */
#[Table('report_schedules')]
#[Fillable([
    'report_template_id',
    'owner_id',
    'name',
    'frequency',
    'format',
    'filters',
    'notify_email',
    'is_active',
    'next_run_at',
    'last_run_at',
    'last_run_id',
])]
class ReportSchedule extends Model
{
    protected $casts = [
        'filters' => 'array',
        'notify_email' => 'boolean',
        'is_active' => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    /** @return BelongsTo<ReportTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<ReportRun, $this> */
    public function lastRun(): BelongsTo
    {
        return $this->belongsTo(ReportRun::class, 'last_run_id');
    }

    /** @return HasMany<ReportRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(ReportRun::class, 'report_schedule_id');
    }
}
