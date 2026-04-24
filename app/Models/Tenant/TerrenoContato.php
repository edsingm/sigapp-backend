<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerrenoContato extends Model
{
    use HasFactory;

    protected $table = 'terreno_contatos';

    protected $fillable = [
        'terreno_id',
        'nome',
        'cargo',
        'telefone',
        'email',
        'observacoes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'terreno_id' => 'int',
        'created_by' => 'int',
        'updated_by' => 'int',
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }
}
