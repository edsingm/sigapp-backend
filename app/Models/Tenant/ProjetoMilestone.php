<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Database\Factories\Tenant\ProjetoMilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $projeto_id
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property Carbon|null $planned_start
 * @property Carbon|null $planned_end
 * @property Carbon|null $predicted_start
 * @property Carbon|null $predicted_end
 * @property Carbon|null $actual_start
 * @property Carbon|null $actual_end
 * @property int|null $responsible_id
 * @property int $weight
 * @property int $position
 * @property bool $is_critical
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $responsavel
 */
#[Table('projeto_milestones')]
#[Fillable(['projeto_id', 'name', 'description', 'status', 'planned_start', 'planned_end', 'predicted_start', 'predicted_end', 'actual_start', 'actual_end', 'responsible_id', 'weight', 'position', 'is_critical'])]
class ProjetoMilestone extends Model
{
    /** @use HasFactory<ProjetoMilestoneFactory> */
    use HasFactory;

    protected $casts = [
        'planned_start' => 'date',
        'planned_end' => 'date',
        'predicted_start' => 'date',
        'predicted_end' => 'date',
        'actual_start' => 'date',
        'actual_end' => 'date',
        'weight' => 'integer',
        'position' => 'integer',
        'is_critical' => 'boolean',
    ];

    /** @return BelongsTo<Projeto, $this> */
    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /** @return HasMany<ProjetoDependency, $this> */
    public function dependenciasComoPredecessor(): HasMany
    {
        return $this->hasMany(ProjetoDependency::class, 'predecessor_milestone_id');
    }

    /** @return HasMany<ProjetoDependency, $this> */
    public function dependenciasComoSucessor(): HasMany
    {
        return $this->hasMany(ProjetoDependency::class, 'successor_milestone_id');
    }
}
