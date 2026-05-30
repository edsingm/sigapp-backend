<?php

namespace App\Models\Tenant;

use App\Enums\PerfilFinanciamento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('premissas_viabilidade')]
#[Fillable(['nome', 'perfil_financiamento', 'ativo', 'versao', 'vigente_em', 'encerrada_em', 'pis_cofins', 'iss', 'outros_impostos', 'comissao', 'parceria_vgv', 'infra_nao_incidente', 'incorporacao', 'incorp_ri', 'incorp_entrega', 'incorp_ate_lancamento', 'obra_ate_lancamento', 'area_comum', 'contrapartidas', 'canteiro_mensal', 'mo_administrativa', 'seguros', 'assistencia_tecnica', 'despesas_comerciais', 'stand_vendas', 'mobilia_decoracao', 'gastos_mensais_stand', 'comissao_house_percentual', 'comissao_imobiliarias_percentual', 'percentual_vendas_house', 'construcao_stand_meses_antes_lancamento', 'ajuda_custo_gerente', 'ajuda_custo_gerente_regional', 'reembolso_logistica', 'bonus_cca', 'bonus_gerente', 'bonus_gerente_regional', 'bonus_credito', 'bonus_gestor_comercial', 'bonus_equipe_comercial', 'pagamento_comissao_venda', 'pagamento_comissao_desligamento', 'parcelamento_comissao_meses', 'parcelamento_comissao_terreno', 'marketing', 'marketing_lancamento', 'marketing_inicio_antes_lancamento', 'itbi_iptu', 'registro', 'custo_contratacao_cef', 'custo_medicao_cef', 'contratos_cef', 'produtos_cef', 'outras_despesas_financeiras', 'despesas_onerosas_bancos', 'prazo_obra', 'compra_terreno', 'porcentagem_lote_proprietario', 'taxa_juros_pj', 'carencia_pj_meses', 'amortizacao_pj_parcelas', 'percentual_antecipacao_pj', 'aporte_adicional_mensal', 'devolucao_aporte_percentual', 'distribuicao_lucros_percentual_obra', 'taxa_exposicao_aplicada', 'inadimplencia', 'atraso_meses', 'taxa_perda', 'meses_incorporacao', 'meses_lancamento', 'meses_entrega', 'meses_pos_obra', 'variavel_correcao'])]
/**
 * @property int $id
 * @property string|null $nome
 * @property string $perfil_financiamento
 * @property bool $ativo
 * @property int $versao
 * @property string|null $vigente_em
 * @property string|null $encerrada_em
 * @property float $pis_cofins
 * @property float $iss
 * @property float $outros_impostos
 * @property float $comissao
 * @property float $parceria_vgv
 * @property float $infra_nao_incidente
 * @property float $incorporacao
 * @property float $incorp_ri
 * @property float $incorp_entrega
 * @property float $incorp_ate_lancamento
 * @property float $obra_ate_lancamento
 * @property float $area_comum
 * @property float $contrapartidas
 * @property float $canteiro_mensal
 * @property float $mo_administrativa
 * @property float $seguros
 * @property float $assistencia_tecnica
 * @property float $despesas_comerciais
 * @property float $stand_vendas
 * @property float $mobilia_decoracao
 * @property float $gastos_mensais_stand
 * @property float $comissao_house_percentual
 * @property float $comissao_imobiliarias_percentual
 * @property float $percentual_vendas_house
 * @property int $construcao_stand_meses_antes_lancamento
 * @property float $ajuda_custo_gerente
 * @property float $ajuda_custo_gerente_regional
 * @property float $reembolso_logistica
 * @property float $bonus_cca
 * @property float $bonus_gerente
 * @property float $bonus_gerente_regional
 * @property float $bonus_credito
 * @property float $bonus_gestor_comercial
 * @property float $bonus_equipe_comercial
 * @property float $pagamento_comissao_venda
 * @property float $pagamento_comissao_desligamento
 * @property int $parcelamento_comissao_meses
 * @property int $parcelamento_comissao_terreno
 * @property float $marketing
 * @property float $marketing_lancamento
 * @property int $marketing_inicio_antes_lancamento
 * @property float $itbi_iptu
 * @property float $registro
 * @property float $custo_contratacao_cef
 * @property float $custo_medicao_cef
 * @property float $contratos_cef
 * @property float $produtos_cef
 * @property float $outras_despesas_financeiras
 * @property float $despesas_onerosas_bancos
 * @property int $prazo_obra
 * @property float $compra_terreno
 * @property float $porcentagem_lote_proprietario
 * @property float $taxa_juros_pj
 * @property int $carencia_pj_meses
 * @property int $amortizacao_pj_parcelas
 * @property float $percentual_antecipacao_pj
 * @property float $aporte_adicional_mensal
 * @property float $devolucao_aporte_percentual
 * @property float $distribuicao_lucros_percentual_obra
 * @property float $taxa_exposicao_aplicada
 * @property float $inadimplencia
 * @property int $atraso_meses
 * @property float $taxa_perda
 * @property int $meses_incorporacao
 * @property int $meses_lancamento
 * @property int $meses_entrega
 * @property int $meses_pos_obra
 * @property float $variavel_correcao
 */
class PremissasViabilidade extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'ativo' => 'boolean',
        'versao' => 'integer',
        'vigente_em' => 'date',
        'encerrada_em' => 'date',
        'perfil_financiamento' => PerfilFinanciamento::class,
        'pis_cofins' => 'decimal:4',
        'iss' => 'decimal:4',
        'outros_impostos' => 'decimal:4',
        'comissao' => 'decimal:4',
        'parceria_vgv' => 'decimal:4',
        'infra_nao_incidente' => 'decimal:4',
        'incorporacao' => 'decimal:4',
        'incorp_ri' => 'decimal:4',
        'incorp_entrega' => 'decimal:4',
        'incorp_ate_lancamento' => 'decimal:4',
        'obra_ate_lancamento' => 'decimal:4',
        'area_comum' => 'decimal:2',
        'contrapartidas' => 'decimal:4',
        'canteiro_mensal' => 'decimal:2',
        'mo_administrativa' => 'decimal:2',
        'seguros' => 'decimal:4',
        'assistencia_tecnica' => 'decimal:4',
        'despesas_comerciais' => 'decimal:4',
        'stand_vendas' => 'decimal:2',
        'mobilia_decoracao' => 'decimal:2',
        'gastos_mensais_stand' => 'decimal:4',
        'comissao_house_percentual' => 'decimal:2',
        'comissao_imobiliarias_percentual' => 'decimal:2',
        'percentual_vendas_house' => 'decimal:2',
        'construcao_stand_meses_antes_lancamento' => 'integer',
        'ajuda_custo_gerente' => 'decimal:2',
        'ajuda_custo_gerente_regional' => 'decimal:2',
        'reembolso_logistica' => 'decimal:2',
        'bonus_cca' => 'decimal:2',
        'bonus_gerente' => 'decimal:4',
        'bonus_gerente_regional' => 'decimal:4',
        'bonus_credito' => 'decimal:4',
        'bonus_gestor_comercial' => 'decimal:4',
        'bonus_equipe_comercial' => 'decimal:2',
        'pagamento_comissao_venda' => 'decimal:2',
        'pagamento_comissao_desligamento' => 'decimal:4',
        'parcelamento_comissao_meses' => 'integer',
        'parcelamento_comissao_terreno' => 'integer',
        'marketing' => 'decimal:4',
        'marketing_lancamento' => 'decimal:2',
        'marketing_inicio_antes_lancamento' => 'integer',
        'itbi_iptu' => 'decimal:4',
        'registro' => 'decimal:2',
        'custo_contratacao_cef' => 'decimal:2',
        'custo_medicao_cef' => 'decimal:2',
        'contratos_cef' => 'decimal:2',
        'produtos_cef' => 'decimal:4',
        'outras_despesas_financeiras' => 'decimal:2',
        'despesas_onerosas_bancos' => 'decimal:4',
        'prazo_obra' => 'integer',
        'compra_terreno' => 'decimal:2',
        'porcentagem_lote_proprietario' => 'decimal:4',
        'taxa_juros_pj' => 'decimal:4',
        'carencia_pj_meses' => 'integer',
        'amortizacao_pj_parcelas' => 'integer',
        'percentual_antecipacao_pj' => 'decimal:4',
        'aporte_adicional_mensal' => 'decimal:2',
        'devolucao_aporte_percentual' => 'decimal:4',
        'distribuicao_lucros_percentual_obra' => 'decimal:4',
        'taxa_exposicao_aplicada' => 'decimal:4',
        'inadimplencia' => 'decimal:4',
        'atraso_meses' => 'integer',
        'taxa_perda' => 'decimal:4',
        'meses_incorporacao' => 'integer',
        'meses_lancamento' => 'integer',
        'meses_entrega' => 'integer',
        'meses_pos_obra' => 'integer',
        'variavel_correcao' => 'decimal:6',
    ];

    protected $attributes = [
        'perfil_financiamento' => 'cef',
        'ativo' => true,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePorPerfil(Builder $query, string $perfil): Builder
    {
        return $query->where('perfil_financiamento', $perfil);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVigente(Builder $query): Builder
    {
        $hoje = now()->toDateString();

        return $query->where(function (Builder $q) use ($hoje): void {
            $q->where('vigente_em', '<=', $hoje)
                ->orWhereNull('vigente_em');
        })->where(function (Builder $q) use ($hoje): void {
            $q->where('encerrada_em', '>=', $hoje)
                ->orWhereNull('encerrada_em');
        });
    }

    public static function carregarAtiva(?string $perfil = null): ?self
    {
        /** @var Builder<self> $query */
        $query = static::query()
            ->where('ativo', true)
            ->where(function (Builder $q): void {
                $hoje = now()->toDateString();
                $q->where('vigente_em', '<=', $hoje)
                    ->orWhereNull('vigente_em');
            })
            ->where(function (Builder $q): void {
                $hoje = now()->toDateString();
                $q->where('encerrada_em', '>=', $hoje)
                    ->orWhereNull('encerrada_em');
            })
            ->orderBy('vigente_em', 'desc');

        if ($perfil !== null) {
            $query->where('perfil_financiamento', $perfil);
        }

        return $query->first();
    }
}
