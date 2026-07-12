<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Database\Factories\Tenant\ProjetoRiskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $projeto_id
 * @property string $title
 * @property string|null $description
 * @property string $probability
 * @property string $impact
 * @property string $severity
 * @property string $status
 * @property string|null $mitigation
 * @property int|null $responsible_id
 * @property Carbon|null $due_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $responsavel
 */
#[Table('projeto_risks')]
#[Fillable(['projeto_id', 'title', 'description', 'probability', 'impact', 'severity', 'status', 'mitigation', 'responsible_id', 'due_date'])]
class ProjetoRisk extends Model
{
    /** @use HasFactory<ProjetoRiskFactory> */
    use HasFactory;

    protected $casts = ['due_date' => 'date'];

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
}
