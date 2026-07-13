<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Database\Factories\Tenant\ContratoCondicaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $contrato_id
 * @property string $title
 * @property string|null $description
 * @property int|null $responsible_id
 * @property Carbon|null $due_date
 * @property string $status
 * @property int|null $evidence_document_id
 * @property Carbon|null $fulfilled_at
 */
#[Table('contrato_condicoes')]
#[Fillable(['contrato_id', 'title', 'description', 'responsible_id', 'due_date', 'status', 'evidence_document_id', 'fulfilled_at'])]
class ContratoCondicao extends Model
{
    /** @use HasFactory<ContratoCondicaoFactory> */
    use HasFactory;

    protected $casts = [
        'due_date' => 'date',
        'fulfilled_at' => 'datetime',
    ];

    /** @return BelongsTo<Contrato, $this> */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /** @return BelongsTo<Documento, $this> */
    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'evidence_document_id');
    }
}
