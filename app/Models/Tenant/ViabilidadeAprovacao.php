<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViabilidadeAprovacao extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'viabilidade_aprovacoes';

    protected $fillable = [
        'viabilidade_id',
        'user_id',
        'decision',
        'comments',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function viabilidade(): BelongsTo
    {
        return $this->belongsTo(Viabilidade::class, 'viabilidade_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
