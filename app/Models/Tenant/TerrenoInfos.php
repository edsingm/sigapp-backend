<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class TerrenoInfos extends Model
{
    protected $table = 'terreno_infos';

    protected $fillable = ['terreno_id', 'descricao', 'created_by', 'user_id'];

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
