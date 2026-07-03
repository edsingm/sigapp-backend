<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('ai_generated_reports')]
#[Fillable(['terreno_id', 'nome', 'file_path', 'tamanho', 'created_by'])]
/**
 * @property int $id
 * @property int|null $terreno_id
 * @property string $nome
 * @property string $file_path
 * @property int $tamanho
 * @property int|null $created_by
 */
class AiGeneratedReport extends Model
{
    use HasFactory;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
