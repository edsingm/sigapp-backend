<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Produto extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * O método "booted" do modelo.
     */
    protected static function booted(): void
    {
        static::saved(function (Produto $model) {
            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:produtos"])->flush();
        });

        static::deleted(function (Produto $model) {
            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:produtos"])->flush();
        });

        static::restored(function (Produto $model) {
            $tenantId = tenant('id') ?? 'central';
            \Illuminate\Support\Facades\Cache::tags(["tenant:{$tenantId}:produtos"])->flush();
        });
    }

    /**
     * Os atributos que podem ser atribuídos em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'image',
        'private_area',
        'm2_cost',
        'infra_cost',
        'status',
        'sinal',
        'parcela_obra',
        'parcela_posChave',
        'qtde_parcelas_posChave',
        'demanda_minCef',
        'defasagem_pgtoTerreno',
        'avaliacao_lotesCef',
        'juros_mensalSinal',
        'juros_mensalObra',
        'juros_mensalPosChave',
        'correcao_anualSinal',
        'correcao_anualObra',
        'correcao_anualPosChave',
        'imposto_tributos',
        'imposto_iss',
        'imposto_outros',
        'curva_vendas',
        'curva_obra',
        'incorp_ri',
        'incorp_entrega',
        'incorp_ateLancamento',
        'obra_ateLancamento',
        'assist_tecnica1',
        'assist_tecnica2',
        'assist_tecnica3',
        'assist_tecnica4',
        'assist_tecnica5',
        'meses_inicioConstrucao',
        'porcentagem_ConstrucaoStand',
        'gastos_mensaisStand',
        'comissao_house',
        'porcentagem_comissaoHouse',
        'porcentagem_comissaoImobs',
        'pagto_comissaoNaVenda',
        'marketing_antesLancamento',
        'marketing_lancamento',
        'custo_contratacaoCef',
        'pj_taxaJuros',
        'pj_carenciaPosObra',
        'pj_qtdeParcelasPosCarencia',
    ];

    protected $casts = [
        'curva_vendas' => 'array',
        'curva_obra'   => 'array',
    ];
}
