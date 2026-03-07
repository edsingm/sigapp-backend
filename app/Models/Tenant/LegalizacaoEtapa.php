<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasDashboardCache;

class LegalizacaoEtapa extends Model
{
    use HasFactory, SoftDeletes, HasDashboardCache;

    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_EM_ANDAMENTO = 'em_andamento';
    public const STATUS_CONCLUIDA = 'concluida';
    public const STATUS_BLOQUEADA = 'bloqueada';
    public const STATUS_ATRASADA = 'atrasada';

    protected $table = 'legalizacao_etapas';

    protected $fillable = [
        'legalizacao_id',
        'parent_id',
        'titulo',
        'descricao',
        'ordem',
        'status',
        'inicio_planejado',
        'fim_planejado',
        'inicio_real',
        'fim_real',
        'percentual',
        'responsavel_id',
        'cor',
        'tipo_custo',
        'valor_custo',
        'custo_pago',
        'custos',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'inicio_planejado' => 'date',
        'fim_planejado' => 'date',
        'inicio_real' => 'date',
        'fim_real' => 'date',
        'ordem' => 'integer',
        'percentual' => 'integer',
        'valor_custo' => 'decimal:2',
        'custo_pago' => 'boolean',
        'custos' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (LegalizacaoEtapa $etapa) {
            $etapa->clearTenantCache('legalizacao_etapas');
            $etapa->legalizacao->calculateProgress();
        });

        static::deleted(function (LegalizacaoEtapa $etapa) {
            $etapa->clearTenantCache('legalizacao_etapas');
            $etapa->legalizacao->calculateProgress();
        });

        static::restored(function (LegalizacaoEtapa $etapa) {
            $etapa->clearTenantCache('legalizacao_etapas');
            $etapa->legalizacao->calculateProgress();
        });
    }

    public function legalizacao(): BelongsTo
    {
        return $this->belongsTo(Legalizacao::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function dependenciasOrigem(): HasMany
    {
        return $this->hasMany(LegalizacaoDependencia::class, 'etapa_origem_id');
    }

    public function dependenciasDestino(): HasMany
    {
        return $this->hasMany(LegalizacaoDependencia::class, 'etapa_destino_id');
    }

    public function dependencias(): HasMany
    {
        return $this->dependenciasDestino();
    }

    public function dependentes()
    {
        return self::whereIn('id', function ($query) {
            $query->select('etapa_destino_id')
                ->from('legalizacao_dependencias')
                ->where('etapa_origem_id', $this->id);
        })->get();
    }

    public function predecessores()
    {
        return self::whereIn('id', function ($query) {
            $query->select('etapa_origem_id')
                ->from('legalizacao_dependencias')
                ->where('etapa_destino_id', $this->id);
        })->get();
    }
}
