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
 * @property int|null $owner_id
 * @property string $name
 * @property string $scope
 * @property array<string, mixed> $definition
 * @property int $version
 * @property bool $is_system
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $owner
 */
#[Table('report_templates')]
#[Fillable(['owner_id', 'name', 'scope', 'definition', 'version', 'is_system'])]
class ReportTemplate extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $casts = [
        'definition' => 'array',
        'version' => 'integer',
        'is_system' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<ReportRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(ReportRun::class, 'report_template_id');
    }
}
