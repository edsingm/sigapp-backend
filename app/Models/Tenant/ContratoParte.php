<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratoParte extends Model
{
    use HasFactory;

    protected $table = 'contrato_partes';

    protected $fillable = [
        'contrato_id',
        'name',
        'document',
        'party_type',
        'signer_name',
        'signer_document',
    ];

    protected $casts = [
        'contrato_id' => 'int',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
