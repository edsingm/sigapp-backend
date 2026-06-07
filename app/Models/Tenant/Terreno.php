<?php

namespace App\Models\Tenant;

use App\Enums\DeclividadeClassificacao;
use App\Enums\ProjetoStatus;
use App\Enums\WorkflowStatus;
use App\Models\Central\Cidade;
use App\Traits\HasDashboardCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

#[Table('terrenos')]
#[Fillable(['nome', 'responsavel_id', 'endereco', 'corretor_id', 'estado', 'cidade_code', 'polygon_coords', 'static_map_url', 'area_calculada', 'area_total', 'area_declividade', 'area_app', 'area_util', 'percentual_aproveitamento', 'declividade_classificacao', 'declividade_avaliacao', 'declividade_impacto_custo', 'declividade_percentual_maximo', 'declividade_percentual_medio', 'app_polygons', 'steep_polygons', 'municipio_ibge_codigo', 'municipio_nome', 'estado_sigla', 'estado_nome', 'regiao_nome', 'mesorregiao_nome', 'microrregiao_nome', 'area_calculada_em', 'area_calculo_status', 'regional_id', 'workflow_stage', 'workflow_status_code', 'workflow_status_changed_at', 'workflow_reason_code', 'workflow_reason_notes', 'qualification_data', 'qualification_completed_at', 'qualification_completed_by', 'cep', 'bairro', 'observacoes', 'valor', 'zona', 'distrito', 'operacao_urbana', 'data_apresentacao', 'data_negociacao', 'data_opcao', 'data_descarte', 'data_contrato', 'comprador_id', 'created_by', 'updated_by'])]
/**
 * @property int $id
 * @property string $nome
 * @property string|null $declividade_impacto_custo
 * @property float|int|null $declividade_percentual_maximo
 * @property float|int|null $declividade_percentual_medio
 * @property array<int, mixed>|null $app_polygons
 * @property array<int, mixed>|null $steep_polygons
 * @property string|null $workflow_stage
 * @property string|null $workflow_status_code
 * @property \Carbon\Carbon|null $workflow_status_changed_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenant\TerrenoProduto> $terrenoProdutos
 * @property-read \App\Models\Tenant\Viabilidade|null $viabilidadeAtual
 * @property-read \App\Models\Tenant\ComiteRevisao|null $comiteAtual
 * @property-read \App\Models\Tenant\Legalizacao|null $legalizacao
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenant\Task> $tasks
 */
class Terreno extends Model
{
    use HasDashboardCache, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saved(function (Terreno $terreno) {
            $terreno->clearTenantCache('terrenos');
            $terreno->clearTenantCache('legalizacoes');
            $terreno->clearTenantCache('projetos');

            $tenantId = tenant('id') ?? 'central';
            Cache::tags(["tenant:{$tenantId}:dashboard"])->flush();
        });

        static::deleted(function (Terreno $terreno) {
            $terreno->clearTenantCache('terrenos');
            $terreno->clearTenantCache('legalizacoes');
            $terreno->clearTenantCache('projetos');

            $tenantId = tenant('id') ?? 'central';
            Cache::tags(["tenant:{$tenantId}:dashboard"])->flush();
        });

        static::restored(function (Terreno $terreno) {
            $terreno->clearTenantCache('terrenos');
            $terreno->clearTenantCache('legalizacoes');
            $terreno->clearTenantCache('projetos');

            $tenantId = tenant('id') ?? 'central';
            Cache::tags(["tenant:{$tenantId}:dashboard"])->flush();
        });
    }

    protected $casts = [
        'polygon_coords' => 'array',
        'area_calculada' => 'decimal:2',
        'area_total' => 'decimal:2',
        'area_declividade' => 'decimal:2',
        'area_app' => 'decimal:2',
        'area_util' => 'decimal:2',
        'declividade_percentual_maximo' => 'decimal:2',
        'declividade_percentual_medio' => 'decimal:2',
        'app_polygons' => 'array',
        'steep_polygons' => 'array',
        'declividade_classificacao' => DeclividadeClassificacao::class,
        'percentual_aproveitamento' => 'decimal:2',
        'area_calculada_em' => 'datetime',
        'valor' => 'decimal:2',
        'qualification_data' => 'array',
        'workflow_status_changed_at' => 'datetime',
        'qualification_completed_at' => 'datetime',
        'data_apresentacao' => 'date',
        'data_negociacao' => 'date',
        'data_opcao' => 'date',
        'data_descarte' => 'date',
        'data_contrato' => 'date',
    ];

    /**
     * Obtém o usuário responsável pela área.
     */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    /**
     * Obtém o status do terreno via WorkflowStatus enum.
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn () => WorkflowStatus::tryFrom($this->workflow_status_code) ?? WorkflowStatus::EM_ANALISE,
        );
    }

    /**
     * Obtém o comprador da área.
     */
    public function comprador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comprador_id');
    }

    /**
     * Obtém o usuário que criou o terreno.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Obtém o usuário que atualizou o terreno.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Aliases para consistência entre recursos/serviços.
     */
    public function createdBy(): BelongsTo
    {
        return $this->creator();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->updater();
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
     * Referência central da cidade (pelo código IBGE).
     */
    public function cidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class, 'cidade_code', 'code');
    }

    /**
     * Coleções relacionadas.
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
        return $this->hasOne(Viabilidade::class, 'terreno_id')
            ->where('is_current', true)
            ->latestOfMany();
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

    public function contatos(): HasMany
    {
        return $this->hasMany(TerrenoContato::class, 'terreno_id');
    }

    public function legalizacao(): HasOne
    {
        return $this->hasOne(Legalizacao::class, 'terreno_id');
    }

    public function projetos(): HasMany
    {
        return $this->hasMany(Projeto::class, 'terreno_id');
    }

    public function comiteRevisoes(): HasMany
    {
        return $this->hasMany(ComiteRevisao::class, 'terreno_id');
    }

    public function comiteAtual(): HasOne
    {
        return $this->hasOne(ComiteRevisao::class, 'terreno_id')->latestOfMany();
    }

    public function negociacoes(): HasMany
    {
        return $this->hasMany(Negociacao::class, 'terreno_id');
    }

    public function negociacaoAtual(): HasOne
    {
        return $this->hasOne(Negociacao::class, 'terreno_id')->latestOfMany();
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'terreno_id');
    }

    public function contratoAtual(): HasOne
    {
        return $this->hasOne(Contrato::class, 'terreno_id')->latestOfMany();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'terreno_id')->orderByDesc('created_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'terreno_id')->orderBy('due_date');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'terreno_id')->orderByDesc('created_at');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(EntityActivity::class, 'terreno_id')->orderByDesc('happened_at');
    }

    public function projetoAtivo(): HasOne
    {
        return $this->hasOne(Projeto::class, 'terreno_id')
            ->whereIn('status', [
                ProjetoStatus::EM_VIABILIDADE,
                ProjetoStatus::EM_LEGALIZACAO,
            ])
            ->latestOfMany();
    }
}
