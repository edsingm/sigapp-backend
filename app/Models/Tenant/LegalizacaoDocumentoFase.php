<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('legalizacao_documentos_fase')]
#[Fillable(['legalizacao_etapa_id', 'title', 'file_path', 'category', 'status', 'is_required', 'verified_by', 'verified_at', 'notes'])]
class LegalizacaoDocumentoFase extends Model
{
    use HasFactory;

    protected $casts = [
        'is_required' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(LegalizacaoEtapa::class, 'legalizacao_etapa_id');
    }
}
