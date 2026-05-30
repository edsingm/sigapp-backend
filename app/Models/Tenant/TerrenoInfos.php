<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('terreno_infos')]
#[Fillable(['terreno_id', 'descricao', 'created_by', 'user_id'])]
class TerrenoInfos extends Model
{
    /**
     * @var array<string, string>
     */
    protected $casts = [
        'created_by' => 'int',
        'user_id' => 'int',
    ];

    /**
     * @return BelongsTo<Terreno, $this>
     */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
