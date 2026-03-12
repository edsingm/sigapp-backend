<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalizacaoDocumentoFase extends Model
{
    use HasFactory;

    protected $table = 'legalizacao_documentos_fase';

    protected $fillable = [
        'legalizacao_etapa_id',
        'title',
        'file_path',
        'category',
        'status',
        'is_required',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(LegalizacaoEtapa::class, 'legalizacao_etapa_id');
    }
}
