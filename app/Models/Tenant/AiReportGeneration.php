<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\AiReportGenerationStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $terreno_id
 * @property int|null $requested_by
 * @property AiReportGenerationStatus $status
 * @property int $progress
 * @property int|null $report_id
 * @property string|null $error_message
 * @property Carbon|null $requested_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
#[Table('ai_report_generations')]
#[Fillable(['terreno_id', 'requested_by', 'status', 'progress', 'report_id', 'error_message', 'requested_at', 'started_at', 'completed_at'])]
class AiReportGeneration extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $casts = [
        'status' => AiReportGenerationStatus::class,
        'progress' => 'integer',
        'requested_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<Terreno, $this> */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<AiGeneratedReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(AiGeneratedReport::class, 'report_id');
    }
}
