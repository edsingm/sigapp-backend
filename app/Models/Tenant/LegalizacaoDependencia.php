<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasDashboardCache;

class LegalizacaoDependencia extends Model
{
    use HasFactory, HasDashboardCache;

    protected $table = 'legalizacao_dependencias';

    protected $fillable = [
        'legalizacao_id',
        'etapa_origem_id',
        'etapa_destino_id',
        'tipo',
    ];

    protected $casts = [
        'tipo' => 'string',
    ];

    protected static function booted(): void
    {
        static::saved(function (LegalizacaoDependencia $dependencia) {
            $dependencia->clearTenantCache('legalizacao_dependencias');
        });

        static::deleted(function (LegalizacaoDependencia $dependencia) {
            $dependencia->clearTenantCache('legalizacao_dependencias');
        });

    }

    public function legalizacao(): BelongsTo
    {
        return $this->belongsTo(Legalizacao::class);
    }

    public function etapaOrigem(): BelongsTo
    {
        return $this->belongsTo(LegalizacaoEtapa::class, 'etapa_origem_id');
    }

    public function etapaDestino(): BelongsTo
    {
        return $this->belongsTo(LegalizacaoEtapa::class, 'etapa_destino_id');
    }
}
