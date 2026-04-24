<?php

namespace App\Models\Tenant;

use App\Enums\LegalizacaoEtapaStatus;
use App\Traits\HasDashboardCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegalizacaoEtapa extends Model
{
    use HasDashboardCache, HasFactory, SoftDeletes;

    protected $table = 'legalizacao_etapas';

    protected $fillable = [
        'legalizacao_id',
        'parent_id',
        'phase_code',
        'subphase_code',
        'is_required',
        'is_critical',
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
        'status' => LegalizacaoEtapaStatus::class,
        'inicio_planejado' => 'date',
        'fim_planejado' => 'date',
        'inicio_real' => 'date',
        'fim_real' => 'date',
        'ordem' => 'integer',
        'is_required' => 'boolean',
        'is_critical' => 'boolean',
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

    public function documentos(): HasMany
    {
        return $this->hasMany(LegalizacaoDocumentoFase::class, 'legalizacao_etapa_id');
    }

    public function pendencias(): HasMany
    {
        return $this->hasMany(LegalizacaoPendencia::class, 'legalizacao_etapa_id');
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