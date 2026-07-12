<?php

namespace App\Models\Tenant;

use Carbon\Carbon;
use Database\Factories\Tenant\NegociacaoOfertaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $negociacao_id
 * @property int $version
 * @property string $offer_type
 * @property string|null $amount
 * @property string|null $business_model
 * @property array<string, mixed>|null $terms
 * @property string $status
 * @property Carbon|null $valid_until
 * @property int|null $previous_offer_id
 * @property int|null $created_by
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 */
#[Table('negociacao_ofertas')]
#[Fillable(['negociacao_id', 'version', 'offer_type', 'amount', 'business_model', 'terms', 'status', 'valid_until', 'previous_offer_id', 'created_by', 'accepted_at', 'rejected_at'])]
class NegociacaoOferta extends Model
{
    /** @use HasFactory<NegociacaoOfertaFactory> */
    use HasFactory;

    protected $casts = [
        'amount' => 'decimal:2',
        'terms' => 'array',
        'valid_until' => 'date',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /** @return BelongsTo<Negociacao, $this> */
    public function negociacao(): BelongsTo
    {
        return $this->belongsTo(Negociacao::class, 'negociacao_id');
    }

    /** @return BelongsTo<NegociacaoOferta, $this> */
    public function previousOffer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_offer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
