<?php

namespace App\Models\Tenant;

use App\Traits\HasDashboardCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Legalizacao extends Model
{
    use HasFactory, SoftDeletes, HasDashboardCache;

    public const STATUS_PLANEJADO = 'planejado';
    public const STATUS_EM_ANDAMENTO = 'em_andamento';
    public const STATUS_CONCLUIDO = 'concluido';
    public const STATUS_CANCELADO = 'cancelado';

    protected $table = 'legalizacoes';

    protected static function booted(): void
    {
        static::saved(function (Legalizacao $legalizacao) {
            $legalizacao->clearTenantCache('legalizacoes');
            $legalizacao->clearTenantCache('projetos');
        });

        static::deleted(function (Legalizacao $legalizacao) {
            $legalizacao->clearTenantCache('legalizacoes');
            $legalizacao->clearTenantCache('projetos');
        });

        static::restored(function (Legalizacao $legalizacao) {
            $legalizacao->clearTenantCache('legalizacoes');
            $legalizacao->clearTenantCache('projetos');
        });
    }

    protected $fillable = [
        'terreno_id',
        'responsavel_id',
        'nome',
        'status',
        'data_inicio_planejada',
        'data_fim_planejada',
        'data_inicio_prevista',
        'data_conclusao_prevista',
        'data_inicio_real',
        'data_fim_real',
        'percentual_concluido',
        'custo_total_previsto',
        'observacoes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data_inicio_planejada' => 'date',
        'data_fim_planejada' => 'date',
        'data_inicio_prevista' => 'date',
        'data_conclusao_prevista' => 'date',
        'data_inicio_real' => 'date',
        'data_fim_real' => 'date',
        'percentual_concluido' => 'integer',
        'custo_total_previsto' => 'decimal:2',
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
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

    public function etapas(): HasMany
    {
        return $this->hasMany(LegalizacaoEtapa::class)->orderBy('ordem');
    }

    public function dependencias(): HasMany
    {
        return $this->hasMany(LegalizacaoDependencia::class);
    }

    public function pendencias(): HasMany
    {
        return $this->hasMany(LegalizacaoPendencia::class, 'legalizacao_id');
    }

    public function recalcularProgresso(): void
    {
        $this->calculateProgress();
    }

    public function calculateProgress(): void
    {
        $etapas = $this->etapas()->get();

        if ($etapas->isEmpty()) {
            $this->percentual_concluido = 0;
            if ($this->status !== self::STATUS_CANCELADO) {
                $this->status = self::STATUS_PLANEJADO;
            }
            $this->save();
            return;
        }

        $percentualMedio = (int) round((float) $etapas->avg('percentual'));
        $this->percentual_concluido = max(0, min(100, $percentualMedio));

        if ($this->status !== self::STATUS_CANCELADO) {
            $allDone = $etapas->every(fn (LegalizacaoEtapa $etapa) => $etapa->status === LegalizacaoEtapa::STATUS_CONCLUIDA);
            if ($allDone) {
                $this->status = self::STATUS_CONCLUIDO;
            } elseif ($this->percentual_concluido > 0 || $etapas->contains(fn (LegalizacaoEtapa $etapa) => $etapa->status === LegalizacaoEtapa::STATUS_EM_ANDAMENTO)) {
                $this->status = self::STATUS_EM_ANDAMENTO;
            } else {
                $this->status = self::STATUS_PLANEJADO;
            }
        }

        $this->save();
    }

    public function scopeElegivel($query)
    {
        return $query->whereHas('terreno', function ($q) {
            $q->where('workflow_status_code', 'contrato_assinado');
        });
    }
}
