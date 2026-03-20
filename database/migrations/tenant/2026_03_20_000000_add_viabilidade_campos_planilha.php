<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viabilidades', function (Blueprint $table) {
            // Prazos configuráveis (antes eram fixos no config)
            $table->integer('prazo_lancamento')->nullable()->after('prazo_obra');
            $table->integer('prazo_incorporacao')->nullable()->after('prazo_lancamento');

            // Juros PJ - modelo completo
            $table->decimal('taxa_juros_pj', 8, 4)->nullable()->after('despesas_onerosas_bancos');
            $table->decimal('percentual_antecipacao_pj', 8, 4)->nullable()->after('taxa_juros_pj');
            $table->integer('carencia_pj_meses')->nullable()->after('percentual_antecipacao_pj');
            $table->integer('amortizacao_pj_parcelas')->nullable()->after('carencia_pj_meses');

            // Incorporação detalhada
            $table->decimal('incorporacao_ri', 8, 2)->nullable()->after('incorporacao');
            $table->decimal('incorporacao_entrega', 8, 2)->nullable()->after('incorporacao_ri');
            $table->decimal('incorporacao_ate_lancamento', 8, 2)->nullable()->after('incorporacao_entrega');

            // Assistência técnica por anos
            $table->json('assistencia_tecnica_curva')->nullable()->after('assistencia_tecnica');

            // Despesas comerciais detalhadas
            $table->decimal('stand_vendas', 12, 2)->nullable()->after('despesas_comerciais');
            $table->decimal('mobilia_decoracao', 12, 2)->nullable()->after('stand_vendas');
            $table->decimal('gastos_mensais_stand', 8, 4)->nullable()->after('mobilia_decoracao');
            $table->decimal('comissao_house_percentual', 8, 2)->nullable()->after('gastos_mensais_stand');
            $table->decimal('comissao_imobiliarias_percentual', 8, 2)->nullable()->after('comissao_house_percentual');
            $table->decimal('percentual_vendas_house', 8, 2)->nullable()->after('comissao_imobiliarias_percentual');
            $table->decimal('ajuda_custo_gerente', 12, 2)->nullable()->after('percentual_vendas_house');
            $table->decimal('ajuda_custo_gerente_regional', 12, 2)->nullable()->after('ajuda_custo_gerente');
            $table->decimal('reembolso_logistica', 12, 2)->nullable()->after('ajuda_custo_gerente_regional');
            $table->decimal('bonus_cca', 12, 2)->nullable()->after('reembolso_logistica');
            $table->decimal('bonus_gerente', 8, 4)->nullable()->after('bonus_cca');
            $table->decimal('bonus_gerente_regional', 8, 4)->nullable()->after('bonus_gerente');
            $table->decimal('bonus_credito', 8, 4)->nullable()->after('bonus_gerente_regional');
            $table->decimal('bonus_gestor_comercial', 8, 4)->nullable()->after('bonus_credito');
            $table->decimal('pagamento_comissao_venda', 8, 2)->nullable()->after('bonus_gestor_comercial');
            $table->decimal('pagamento_comissao_desligamento', 8, 2)->nullable()->after('pagamento_comissao_venda');
            $table->integer('parcelamento_comissao_meses')->nullable()->after('pagamento_comissao_desligamento');

            // Marketing detalhado
            $table->decimal('marketing_lancamento', 8, 2)->nullable()->after('marketing');
            $table->integer('marketing_inicio_antes_lancamento')->nullable()->after('marketing_lancamento');

            // Aporte e Distribuição de Lucros
            $table->decimal('aporte_adicional_mensal', 12, 2)->nullable()->after('amortizacao_pj_parcelas');
            $table->decimal('devolucao_aporte_percentual', 8, 2)->nullable()->after('aporte_adicional_mensal');
            $table->decimal('distribuicao_lucros_percentual_obra', 8, 2)->nullable()->after('devolucao_aporte_percentual');

            // Taxa para exposição aplicada
            $table->decimal('taxa_exposicao_aplicada', 8, 4)->nullable()->after('distribuicao_lucros_percentual_obra');
        });
    }

    public function down(): void
    {
        Schema::table('viabilidades', function (Blueprint $table) {
            $table->dropColumn([
                'prazo_lancamento',
                'prazo_incorporacao',
                'taxa_juros_pj',
                'percentual_antecipacao_pj',
                'carencia_pj_meses',
                'amortizacao_pj_parcelas',
                'incorporacao_ri',
                'incorporacao_entrega',
                'incorporacao_ate_lancamento',
                'assistencia_tecnica_curva',
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
                'marketing_lancamento',
                'marketing_inicio_antes_lancamento',
                'aporte_adicional_mensal',
                'devolucao_aporte_percentual',
                'distribuicao_lucros_percentual_obra',
                'taxa_exposicao_aplicada',
            ]);
        });
    }
};
