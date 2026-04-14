<?php

namespace App\Models\Tenant;

use App\Traits\HasDashboardCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Projeto extends Model
{
    use HasDashboardCache, HasFactory, SoftDeletes;

    public const STATUS_EM_VIABILIDADE = 'em_viabilidade';

    public const STATUS_EM_LEGALIZACAO = 'em_legalizacao';

    public const STATUS_FINALIZADO = 'finalizado';

    public const STATUS_PRONTO_PARA_REGISTRO = 'pronto_para_registro';

    public const STATUS_CANCELADO = 'cancelado';

    protected $table = 'projetos';

    protected $fillable = [
        'nome',
        'terreno_id',
        'responsavel_id',
        'status',
        'pronto_para_registro_em',
        'pronto_para_registro_por',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
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
