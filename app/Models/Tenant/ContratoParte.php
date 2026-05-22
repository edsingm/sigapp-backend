<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('contrato_partes')]
#[Fillable(['contrato_id', 'name', 'document', 'party_type', 'signer_name', 'signer_document'])]
class ContratoParte extends Model
{
    use HasFactory;

    protected $casts = [
        'contrato_id' => 'int',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
