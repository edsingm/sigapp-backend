<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

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
            Cache::tags(["tenant:{$tenantId}:produtos"])->flush();
        });

        static::deleted(function (Produto $model) {
            $tenantId = tenant('id') ?? 'central';
            Cache::tags(["tenant:{$tenantId}:produtos"])->flush();
        });

        static::restored(function (Produto $model) {
            $tenantId = tenant('id') ?? 'central';
            Cache::tags(["tenant:{$tenantId}:produtos"])->flush();
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
        'curva_vendas',
        'baloes_anuais',
        'balao_entrega_modo',
        'assist_tecnica1',
        'assist_tecnica2',
        'assist_tecnica3',
        'assist_tecnica4',
        'assist_tecnica5',
        'meses_inicioConstrucao',
        'porcentagem_ConstrucaoStand',
    ];

    protected $casts = [
        'private_area' => 'decimal:2',
        'm2_cost' => 'decimal:2',
        'infra_cost' => 'decimal:2',
        'sinal' => 'decimal:2',
        'parcela_obra' => 'decimal:2',
        'parcela_posChave' => 'decimal:2',
        'demanda_minCef' => 'decimal:2',
        'defasagem_pgtoTerreno' => 'decimal:2',
        'avaliacao_lotesCef' => 'decimal:2',
        'juros_mensalSinal' => 'decimal:4',
        'juros_mensalObra' => 'decimal:4',
        'juros_mensalPosChave' => 'decimal:4',
        'correcao_anualSinal' => 'decimal:4',
        'correcao_anualObra' => 'decimal:4',
        'correcao_anualPosChave' => 'decimal:4',
        'meses_inicioConstrucao' => 'int',
        'porcentagem_ConstrucaoStand' => 'decimal:2',
        'curva_vendas' => 'array',
        'baloes_anuais' => 'array',
    ];
}
