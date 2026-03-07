<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Central\Cidade;
use App\Traits\HasDashboardCache;

class Terreno extends Model
{
    use HasFactory, SoftDeletes, HasDashboardCache;

    protected $table = 'terrenos';

    protected static function booted(): void
    {
        static::saved(function (Terreno $terreno) {
            $terreno->clearTenantCache('terrenos');
            $terreno->clearTenantCache('legalizacoes');
            $terreno->clearTenantCache('projetos');

            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:dashboard"])->flush();
        });

        static::deleted(function (Terreno $terreno) {
            $terreno->clearTenantCache('terrenos');
            $terreno->clearTenantCache('legalizacoes');
            $terreno->clearTenantCache('projetos');

            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:dashboard"])->flush();
        });

        static::restored(function (Terreno $terreno) {
            $terreno->clearTenantCache('terrenos');
            $terreno->clearTenantCache('legalizacoes');
            $terreno->clearTenantCache('projetos');

            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:dashboard"])->flush();
        });
    }

    protected $fillable = [
        'nome',
        'responsavel_id',
        'endereco',
        'corretor_id',
        'estado',
        'cidade_code',
        'polygon_coords',
        'static_map_url',
        'area_calculada',
        'status_id',
        'regional_id',
        'cep',
        'bairro',
        'observacoes',
        'valor',
        'zona',
        'distrito',
        'operacao_urbana',
        'data_apresentacao',
        'data_negociacao',
        'data_opcao',
        'data_descarte',
        'data_contrato',
        'comprador_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'polygon_coords' => 'array',
        'area_calculada' => 'decimal:2',
        'valor' => 'decimal:2',
        'data_apresentacao' => 'date',
        'data_negociacao' => 'date',
        'data_opcao' => 'date',
        'data_descarte' => 'date',
        'data_contrato' => 'date',
    ];

    /**
     * Get the user who is responsible for the area.
     */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    /**
     * Get the buyer for the area.
     */
    public function comprador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comprador_id');
    }

    /**
     * Get the user who created the terreno.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who updated the terreno.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Aliases for consistency across resources/services.
     */
    public function createdBy(): BelongsTo
    {
        return $this->creator();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->updater();
    }

    /**
     * Status, regional, and external broker relationships.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TerrenoStatus::class, 'status_id');
    }

    public function terrenoStatus(): BelongsTo
    {
        return $this->status();
    }

    public function regional(): BelongsTo
    {
        return $this->belongsTo(Regional::class, 'regional_id');
    }

    public function corretorExterno(): BelongsTo
    {
        return $this->belongsTo(CorretorExterno::class, 'corretor_id');
    }

    /**
     * Central city reference (by IBGE code).
     */
    public function cidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class, 'cidade_code', 'code');
    }

    /**
     * Related collections.
     */
    public function terrenoProdutos(): HasMany
    {
        return $this->hasMany(TerrenoProduto::class, 'terreno_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'terreno_id');
    }

    public function viabilidades(): HasMany
    {
        return $this->hasMany(Viabilidade::class, 'terreno_id');
    }

    public function viabilidadeAtual(): HasOne
    {
        return $this->hasOne(Viabilidade::class, 'terreno_id')->latestOfMany();
    }

    public function informacoes(): HasMany
    {
        return $this->hasMany(TerrenoInfos::class, 'terreno_id');
    }

    public function terrenoInfos(): HasMany
    {
        return $this->informacoes();
    }

    public function proprietarios(): HasMany
    {
        return $this->hasMany(Proprietario::class, 'terreno_id');
    }

    public function legalizacao(): HasOne
    {
        return $this->hasOne(Legalizacao::class, 'terreno_id');
    }

    public function projetos(): HasMany
    {
        return $this->hasMany(Projeto::class, 'terreno_id');
    }

    public function projetoAtivo(): HasOne
    {
        return $this->hasOne(Projeto::class, 'terreno_id')
            ->whereIn('status', [
                Projeto::STATUS_EM_VIABILIDADE,
                Projeto::STATUS_EM_LEGALIZACAO,
            ])
            ->latestOfMany();
    }

    /**
     * Alias para area_calculada (compatibilidade com templates antigos)
     */
    public function getAreaTotalAttribute()
    {
        return $this->area_calculada;
    }
}
