<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('terreno_infos')]
#[Fillable(['terreno_id', 'descricao', 'created_by', 'user_id'])]
class TerrenoInfos extends Model
{
    protected $casts = [
        'created_by' => 'int',
        'user_id' => 'int',
    ];

    public function terreno()
    {
        return $this->belongsTo(Terreno::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
