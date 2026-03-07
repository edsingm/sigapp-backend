<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Tenant\Terreno;
use App\Traits\LogsActivity;
use App\Traits\HasDashboardCache;


/** Obter todas as viabilidades de uma área
* $area = Area::with('viabilidades')->find(1);
* $todasViabilidades = $area->viabilidades;
*
* Obter apenas a viabilidade mais recente
* $viabilidadeAtual = $area->viabilidadeAtual;
*
* Buscar viabilidade mais recente por área específica
* $ultimaViabilidade = Viabilidade::latestByArea($areaId)->first();

* Buscar apenas viabilidades ativas (mais recentes de cada área)
* $viabilidadesAtivas = Viabilidade::ativas()->with('area')->get();

* Histórico de viabilidades de uma área
* $historico = $area->viabilidades()
*    ->orderBy('created_at', 'desc')
*    ->get();
*/


class Viabilidade extends Model
{
    use HasFactory, SoftDeletes, HasDashboardCache;

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
        'area_comum',
        'contrapartidas',
        'canteiro_mensal',
        'mo_administrativa',
        'seguros',
        'assistencia_tecnica',
        'despesas_comerciais',
        'marketing',
        'itbi_iptu',
        'registro',
        'medicao_contratacao',
        'contratos_cef',
        'produtos_cef',
        'outras_despesas_financeiras',
        'despesas_onerosas_bancos'
    ];

    protected $fillable = [
        'terreno_id',
        'parceria_vgv',
        'compra_terreno',
        'infra_nao_incidente',
        'porcentagem_lote_proprietario',
        'prazo_obra',
        'pis_cofins',
        'iss',
        'outros_impostos',
        'comissao',
        'incorporacao',
        'area_comum',
        'contrapartidas',
        'canteiro_mensal',
        'mo_administrativa',
        'seguros',
        'assistencia_tecnica',
        'despesas_comerciais',
        'marketing',
        'itbi_iptu',
        'registro',
        'medicao_contratacao',
        'contratos_cef',
        'produtos_cef',
        'outras_despesas_financeiras',
        'despesas_onerosas_bancos',
        'resultados_dre',
        'status',
        'approval_status',
        'approval_requested_at',
        'approval_decided_at',
        'approval_decided_by',
        'approval_notes',
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
        'area_comum' => 'decimal:2',
        'contrapartidas' => 'decimal:2',
        'canteiro_mensal' => 'decimal:2',
        'mo_administrativa' => 'decimal:2',
        'seguros' => 'decimal:2',
        'assistencia_tecnica' => 'decimal:2',
        'despesas_comerciais' => 'decimal:2',
        'marketing' => 'decimal:2',
        'itbi_iptu' => 'decimal:2',
        'registro' => 'decimal:2',
        'medicao_contratacao' => 'decimal:2',
        'contratos_cef' => 'decimal:2',
        'produtos_cef' => 'decimal:2',
        'outras_despesas_financeiras' => 'decimal:2',
        'despesas_onerosas_bancos' => 'decimal:2',
        'resultados_dre' => 'array',
        'approval_requested_at' => 'datetime',
        'approval_decided_at' => 'datetime',
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

    /**
     * Relacionamento com a área
     */
    public function terreno(): BelongsTo
    {
        return $this->belongsTo(Terreno::class);
    }

    /**
     * Alias para terreno (compatibilidade com templates antigos)
     */
    public function area(): BelongsTo
    {
        return $this->terreno();
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

    public function isApproved(): bool
    {
        return $this->approval_status === 'aprovada' || $this->status === 'ativo';
    }

    /**
     * Accessor para formatar valores monetários grandes
     */
    public function getParceriaVgvFormatadoAttribute(): string
    {
        return 'R$ ' . number_format((float) $this->parceria_vgv, 2, ',', '.');
    }

    /**
     * Accessor para formatar valor de compra do terreno
     */
    public function getCompraTerrenoFormatadoAttribute(): string
    {
        return 'R$ ' . number_format((float) $this->compra_terreno, 2, ',', '.');
    }

    /**
     * Accessor para formatar percentuais
     */
    public function getPisCofinsPorcentagemAttribute(): string
    {
        return number_format((float) $this->pis_cofins, 2, ',', '.') . '%';
    }

    /**
     * Accessor para obter o prazo de obra em formato legível
     */
    public function getPrazoObraFormatadoAttribute(): string
    {
        if (!$this->prazo_obra) {
            return 'Não definido';
        }

        return $this->prazo_obra . ' meses';
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
        return !empty($this->resultados_dre);
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
