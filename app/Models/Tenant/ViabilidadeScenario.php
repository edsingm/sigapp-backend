<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\ViabilidadeScenarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('viabilidade_scenarios')]
#[Fillable(['viabilidade_id', 'name', 'scenario_type', 'status', 'premises_snapshot', 'results', 'formula_version', 'input_hash', 'created_by', 'calculated_by', 'calculated_at', 'promoted_by', 'promoted_at', 'error_message'])]
/**
 * @property int $id
 * @property int $viabilidade_id
 * @property string $name
 * @property string $scenario_type
 * @property string $status
 * @property array<string, mixed>|null $premises_snapshot
 * @property array<string, mixed>|null $results
 * @property string $formula_version
 * @property string|null $input_hash
 * @property-read Viabilidade|null $viabilidade
 */
class ViabilidadeScenario extends Model
{
    /** @use HasFactory<ViabilidadeScenarioFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'premises_snapshot' => 'array',
            'results' => 'array',
            'calculated_at' => 'datetime',
            'promoted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Viabilidade, $this>
     */
    public function viabilidade(): BelongsTo
    {
        return $this->belongsTo(Viabilidade::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
