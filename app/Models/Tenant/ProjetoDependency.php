<?php

namespace App\Models\Tenant;

use Database\Factories\Tenant\ProjetoDependencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $projeto_id
 * @property int $predecessor_milestone_id
 * @property int $successor_milestone_id
 * @property string $dependency_type
 * @property int $lag_days
 * @property-read ProjetoMilestone|null $predecessor
 * @property-read ProjetoMilestone|null $successor
 */
#[Table('projeto_dependencies')]
#[Fillable(['projeto_id', 'predecessor_milestone_id', 'successor_milestone_id', 'dependency_type', 'lag_days'])]
class ProjetoDependency extends Model
{
    /** @use HasFactory<ProjetoDependencyFactory> */
    use HasFactory;

    protected $casts = ['lag_days' => 'integer'];

    /** @return BelongsTo<Projeto, $this> */
    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class);
    }

    /** @return BelongsTo<ProjetoMilestone, $this> */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(ProjetoMilestone::class, 'predecessor_milestone_id');
    }

    /** @return BelongsTo<ProjetoMilestone, $this> */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(ProjetoMilestone::class, 'successor_milestone_id');
    }
}
