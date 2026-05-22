<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('terreno_contatos')]
#[Fillable(['terreno_id', 'nome', 'cargo', 'telefone', 'email', 'observacoes', 'created_by', 'updated_by'])]
class TerrenoContato extends Model
{
    use HasFactory;

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
