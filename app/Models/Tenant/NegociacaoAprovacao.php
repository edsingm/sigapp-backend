<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Database\Factories\Tenant\NegociacaoAprovacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $negociacao_id
 * @property string $area
 * @property string $decision
 * @property string|null $comment
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 */
#[Table('negociacao_aprovacoes')]
#[Fillable(['negociacao_id', 'area', 'decision', 'comment', 'decided_by', 'decided_at'])]
class NegociacaoAprovacao extends Model
{
    /** @use HasFactory<NegociacaoAprovacaoFactory> */
    use HasFactory;

    protected $casts = ['decided_at' => 'datetime'];

    /** @return BelongsTo<Negociacao, $this> */
    public function negociacao(): BelongsTo
    {
        return $this->belongsTo(Negociacao::class, 'negociacao_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
