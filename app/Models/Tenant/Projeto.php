<?php

namespace App\Models\Tenant;

use App\Enums\ProjetoStatus;
use App\Traits\HasDashboardCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('projetos')]
#[Fillable(['nome', 'terreno_id', 'responsavel_id', 'status', 'pronto_para_registro_em', 'pronto_para_registro_por', 'created_by', 'updated_by'])]
class Projeto extends Model
{
    use HasDashboardCache, HasFactory, SoftDeletes;

    protected $casts = [
        'status' => ProjetoStatus::class,
        'pronto_para_registro_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (Projeto $projeto) {
            $projeto->clearTenantCache('projetos');
        });

        static::deleted(function (Projeto $projeto) {
            $projeto->clearTenantCache('projetos');
        });

        static::restored(function (Projeto $projeto) {
            $projeto->clearTenantCache('projetos');
        });
    }

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class, 'terreno_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function prontoParaRegistroPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pronto_para_registro_por');
    }
}
