<?php

namespace App\Models\Tenant;

use App\Traits\HasDashboardCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Viabilidade extends Model
{
    use HasDashboardCache, HasFactory, SoftDeletes;

    protected $table = 'viabilidades';

    protected static function booted()
    {
        static::saved(function ($model) {
            $model->clearTenantCache('viabilidades');
            $model->clearTenantCache('projetos');
        });

        static::deleted(function ($model) {
            $model->clearTenantCache('viabilidades');
            $model->clearTenantCache('projetos');
        });

        static::restored(function ($model) {
            $model->clearTenantCache('viabilidades');
            $model->clearTenantCache('projetos');
        });
    }

    /**
     * Lista de campos financeiros utilizados para validação e cálculos
     */
    public const CAMPOS_FINANCEIROS = [
        'parceria_vgv',
        'compra_terreno',
        'infra_nao_incidente',
        'porcentagem_lote_proprietario',
        'pis_cofins',
        'iss',
        'outros_impostos',
        'comissao',
        'incorporacao',
        'incorporacao_ri',
        'incorporacao_entrega',
        'incorporacao_ate_lancamento',
        'area_comum',
        'contrapartidas',
        'canteiro_mensal',
        'mo_administrativa',
        'seguros',
        'assistencia_tecnica',
        'despesas_comerciais',
        'stand_vendas',
        'mobilia_decoracao',
        'gastos_mensais_stand',
        'comissao_house_percentual',
        'comissao_imobiliarias_percentual',
        'percentual_vendas_house',
        'ajuda_custo_gerente',
        'ajuda_custo_gerente_regional',
        'reembolso_logistica',
        'bonus_cca',
        'bonus_gerente',
        'bonus_gerente_regional',
        'bonus_credito',
        'bonus_gestor_comercial',
        'pagamento_comissao_venda',
        'pagamento_comissao_desligamento',
        'parcelamento_comissao_meses',
        'marketing',
        'marketing_lancamento',
        'marketing_inicio_antes_lancamento',
        'itbi_iptu',
        'registro',
        'medicao_contratacao',
        'contratos_cef',
        'produtos_cef',
        'outras_despesas_financeiras',
        'despesas_onerosas_bancos',
        'taxa_juros_pj',
        'percentual_antecipacao_pj',
        'carencia_pj_meses',
        'amortizacao_pj_parcelas',
        'aporte_adicional_mensal',
        'devolucao_aporte_percentual',
        'distribuicao_lucros_percentual_obra',
        'taxa_exposicao_aplicada',
    ];

    protected $fillable = [
        'terreno_id',
        'version',
        'is_current',
        'parceria_vgv',
        'compra_terreno',
        'infra_nao_incidente',
        'porcentagem_lote_proprietario',
        'prazo_obra',
        'prazo_lancamento',
        'prazo_incorporacao',
        'pis_cofins',
        'iss',
        'outros_impostos',
        'comissao',
        'incorporacao',
        'incorporacao_ri',
        'incorporacao_entrega',
        'incorporacao_ate_lancamento',
        'area_comum',
        'contrapartidas',
        'canteiro_mensal',
        'mo_administrativa',
        'seguros',
        'assistencia_tecnica',
        'assistencia_tecnica_curva',
        'despesas_comerciais',
        'stand_vendas',
        'mobilia_decoracao',
        'gastos_mensais_stand',
        'comissao_house_percentual',
        'comissao_imobiliarias_percentual',
        'percentual_vendas_house',
        'ajuda_custo_gerente',
        'ajuda_custo_gerente_regional',
        'reembolso_logistica',
        'bonus_cca',
        'bonus_gerente',
        'bonus_gerente_regional',
        'bonus_credito',
        'bonus_gestor_comercial',
        'pagamento_comissao_venda',
        'pagamento_comissao_desligamento',
        'parcelamento_comissao_meses',
        'marketing',
        'marketing_lancamento',
        'marketing_inicio_antes_lancamento',
        'itbi_iptu',
        'registro',
        'medicao_contratacao',
        'contratos_cef',
        'produtos_cef',
        'outras_despesas_financeiras',
        'despesas_onerosas_bancos',
        'taxa_juros_pj',
        'percentual_antecipacao_pj',
        'carencia_pj_meses',
        'amortizacao_pj_parcelas',
        'aporte_adicional_mensal',
        'devolucao_aporte_percentual',
        'distribuicao_lucros_percentual_obra',
        'taxa_exposicao_aplicada',
        'resultados_dre',
        'status',
        'approval_status',
        'approval_requested_at',
        'approval_decided_at',
        'approval_decided_by',
        'approval_notes',
        'submitted_at',
        'locked_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'parceria_vgv' => 'decimal:2',
        'compra_terreno' => 'decimal:2',
        'infra_nao_incidente' => 'decimal:2',
        'porcentagem_lote_proprietario' => 'decimal:2',
        'pis_cofins' => 'decimal:2',
        'iss' => 'decimal:2',
        'outros_impostos' => 'decimal:2',
        'comissao' => 'decimal:2',
        'incorporacao' => 'decimal:2',
        'incorporacao_ri' => 'decimal:2',
        'incorporacao_entrega' => 'decimal:2',
        'incorporacao_ate_lancamento' => 'decimal:2',
        'area_comum' => 'decimal:2',
        'contrapartidas' => 'decimal:2',
        'canteiro_mensal' => 'decimal:2',
        'mo_administrativa' => 'decimal:2',
        'seguros' => 'decimal:2',
        'assistencia_tecnica' => 'decimal:2',
        'assistencia_tecnica_curva' => 'array',
        'despesas_comerciais' => 'decimal:2',
        'stand_vendas' => 'decimal:2',
        'mobilia_decoracao' => 'decimal:2',
        'gastos_mensais_stand' => 'decimal:4',
        'comissao_house_percentual' => 'decimal:2',
        'comissao_imobiliarias_percentual' => 'decimal:2',
        'percentual_vendas_house' => 'decimal:2',
        'ajuda_custo_gerente' => 'decimal:2',
        'ajuda_custo_gerente_regional' => 'decimal:2',
        'reembolso_logistica' => 'decimal:2',
        'bonus_cca' => 'decimal:2',
        'bonus_gerente' => 'decimal:4',
        'bonus_gerente_regional' => 'decimal:4',
        'bonus_credito' => 'decimal:4',
        'bonus_gestor_comercial' => 'decimal:4',
        'pagamento_comissao_venda' => 'decimal:2',
        'pagamento_comissao_desligamento' => 'decimal:2',
        'parcelamento_comissao_meses' => 'integer',
        'marketing' => 'decimal:2',
        'marketing_lancamento' => 'decimal:2',
        'marketing_inicio_antes_lancamento' => 'integer',
        'itbi_iptu' => 'decimal:2',
        'registro' => 'decimal:2',
        'medicao_contratacao' => 'decimal:2',
        'contratos_cef' => 'decimal:2',
        'produtos_cef' => 'decimal:2',
        'outras_despesas_financeiras' => 'decimal:2',
        'despesas_onerosas_bancos' => 'decimal:2',
        'taxa_juros_pj' => 'decimal:4',
        'percentual_antecipacao_pj' => 'decimal:4',
        'carencia_pj_meses' => 'integer',
        'amortizacao_pj_parcelas' => 'integer',
        'aporte_adicional_mensal' => 'decimal:2',
        'devolucao_aporte_percentual' => 'decimal:2',
        'distribuicao_lucros_percentual_obra' => 'decimal:2',
        'taxa_exposicao_aplicada' => 'decimal:4',
        'prazo_lancamento' => 'integer',
        'prazo_incorporacao' => 'integer',
        'resultados_dre' => 'array',
        'is_current' => 'boolean',
        'approval_requested_at' => 'datetime',
        'approval_decided_at' => 'datetime',
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [];

    /**
     * Os valores que devem ser convertidos para arrays.
     *
     * @var array
     */
    protected $attributes = [
        'parceria_vgv' => '0.00',
        'compra_terreno' => '0.00',
        'infra_nao_incidente' => '0.00',
        'porcentagem_lote_proprietario' => '0.00',
        'pis_cofins' => '4.00',
        'iss' => '0.00',
        'outros_impostos' => '0.50',
        'comissao' => '0.00',
        'incorporacao' => '1.00',
        'area_comum' => '2000.00',
        'contrapartidas' => '1.00',
        'canteiro_mensal' => '75000.00',
        'mo_administrativa' => '82000.00',
        'seguros' => '0.50',
        'assistencia_tecnica' => '1.00',
        'despesas_comerciais' => '5.00',
        'marketing' => '1.00',
        'itbi_iptu' => '1.10',
        'registro' => '2500.00',
        'medicao_contratacao' => '2000.00',
        'contratos_cef' => '5000.00',
        'produtos_cef' => '0.50',
        'outras_despesas_financeiras' => '0.30',
        'despesas_onerosas_bancos' => '10.00',
    ];

    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }

    /**
     * Relacionamento com o usuário que criou
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relacionamento com o usuário que atualizou
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvalDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_decided_by');
    }

    public function secoes(): HasMany
    {
        return $this->hasMany(ViabilidadeSecao::class, 'viabilidade_id');
    }

    public function aprovacoes(): HasMany
    {
        return $this->hasMany(ViabilidadeAprovacao::class, 'viabilidade_id')->orderByDesc('created_at');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'aprovada' || $this->status === 'ativo';
    }

    /**
     * Accessor para formatar valores monetários grandes
     */
    public function getParceriaVgvFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->parceria_vgv, 2, ',', '.');
    }

    /**
     * Accessor para formatar valor de compra do terreno
     */
    public function getCompraTerrenoFormatadoAttribute(): string
    {
        return 'R$ '.number_format((float) $this->compra_terreno, 2, ',', '.');
    }

    /**
     * Accessor para formatar percentuais
     */
    public function getPisCofinsPorcentagemAttribute(): string
    {
        return number_format((float) $this->pis_cofins, 2, ',', '.').'%';
    }

    /**
     * Accessor para obter o prazo de obra em formato legível
     */
    public function getPrazoObraFormatadoAttribute(): string
    {
        if (! $this->prazo_obra) {
            return 'Não definido';
        }

        return $this->prazo_obra.' meses';
    }

    /**
     * Scope para buscar viabilidades por terreno
     */
    public function scopeByTerreno($query, $terrenoId)
    {
        return $query->where('terreno_id', $terrenoId);
    }

    /**
     * Scope para buscar viabilidades criadas por usuário específico
     */
    public function scopeByCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope para buscar viabilidades por prazo de obra
     */
    public function scopeByPrazoObra($query, $prazo)
    {
        return $query->where('prazo_obra', $prazo);
    }

    /**
     * Scope para buscar viabilidades criadas em período específico
     */
    public function scopeCreatedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope para buscar a viabilidade mais recente de uma área
     */
    public function scopeLatestByTerreno($query, $terrenoId)
    {
        return $query->where('terreno_id', $terrenoId)->latest('created_at');
    }

    /**
     * Scope para buscar viabilidades ativas (mais recentes por área)
     */
    public function scopeAtivas($query)
    {
        return $query->whereIn('id', function ($subQuery) {
            $subQuery->select('id')
                ->from('viabilidades as v1')
                ->whereRaw('v1.created_at = (SELECT MAX(v2.created_at) FROM viabilidades v2 WHERE v2.terreno_id = v1.terreno_id AND v2.deleted_at IS NULL)')
                ->whereNull('deleted_at');
        });
    }

    /**
     * Accessor para calcular total de impostos
     */
    public function getTotalImpostosAttribute(): float
    {
        return $this->pis_cofins + $this->iss + $this->outros_impostos;
    }

    /**
     * Accessor para calcular total de despesas financeiras
     */
    public function getTotalDespesasFinanceirasAttribute(): float
    {
        return $this->produtos_cef + $this->outras_despesas_financeiras + $this->despesas_onerosas_bancos;
    }

    /**
     * Accessor para verificar se a viabilidade tem DRE calculado
     */
    public function getTemDreCalculadoAttribute(): bool
    {
        return ! empty($this->resultados_dre);
    }

    /**
     * Mutator para garantir que resultados_dre seja sempre um array
     */
    public function setResultadosDreAttribute($value)
    {
        if (is_string($value)) {
            $this->attributes['resultados_dre'] = $value;
        } else {
            $this->attributes['resultados_dre'] = json_encode($value);
        }
    }

    /**
     * Accessor para resultados DRE - trata double encoding
     */
    public function getResultadosDreAttribute($value)
    {
        if (is_null($value)) {
            return [];
        }

        // Se já é um array, retorna como está
        if (is_array($value)) {
            return $value;
        }

        // Primeiro decode
        $decoded = json_decode($value, true);

        // Se o resultado ainda é uma string, faz o segundo decode
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return $decoded ?? [];
    }
}
