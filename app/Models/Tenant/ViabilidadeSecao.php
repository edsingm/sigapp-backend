<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViabilidadeSecao extends Model
{
    use HasFactory;

    protected $table = 'viabilidade_secoes';

    protected $fillable = [
        'viabilidade_id',
        'section_code',
        'section_name',
        'content_json',
        'status',
    ];

    protected $casts = [
        'content_json' => 'array',
    ];

    public function viabilidade(): BelongsTo
    {
        return $this->belongsTo(Viabilidade::class, 'viabilidade_id');
    }
}
