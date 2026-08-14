<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\PrivacyRequestKind;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacySubjectType;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\Central\PrivacyRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $protocol
 * @property PrivacyRequestKind $kind
 * @property PrivacySubjectType $subject_type
 * @property string $subject_email
 * @property string|null $tenant_id
 * @property PrivacyRequestStatus $status
 * @property string|null $legal_hold_reason
 * @property Carbon $received_at
 * @property Carbon $due_at
 * @property int|null $assigned_to
 * @property string|null $notes
 * @property string|null $export_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'protocol',
    'kind',
    'subject_type',
    'subject_email',
    'tenant_id',
    'status',
    'legal_hold_reason',
    'received_at',
    'due_at',
    'assigned_to',
    'notes',
    'export_path',
])]
class PrivacyRequest extends Model
{
    /** @use HasFactory<PrivacyRequestFactory> */
    use CentralConnection, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PrivacyRequestKind::class,
            'subject_type' => PrivacySubjectType::class,
            'status' => PrivacyRequestStatus::class,
            'received_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
