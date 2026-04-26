<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viabilidades', function (Blueprint $table) {
            $table->dropColumn([
                'incorporacao_ri',
                'incorporacao_entrega',
                'incorporacao_ate_lancamento',
                'assistencia_tecnica_curva',
                'gastos_mensais_stand',
                'comissao_house_percentual',
                'comissao_imobiliarias_percentual',
                'percentual_vendas_house',
                'pagamento_comissao_venda',
                'marketing_lancamento',
                'marketing_inicio_antes_lancamento',
                'medicao_contratacao',
                'taxa_juros_pj',
                'carencia_pj_meses',
                'amortizacao_pj_parcelas',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('viabilidades', function (Blueprint $table) {
            $table->decimal('incorporacao_ri', 12, 2)->nullable();
            $table->decimal('incorporacao_entrega', 12, 2)->nullable();
            $table->decimal('incorporacao_ate_lancamento', 12, 2)->nullable();
            $table->json('assistencia_tecnica_curva')->nullable();
            $table->decimal('gastos_mensais_stand', 12, 4)->nullable();
            $table->decimal('comissao_house_percentual', 12, 2)->nullable();
            $table->decimal('comissao_imobiliarias_percentual', 12, 2)->nullable();
            $table->decimal('percentual_vendas_house', 12, 2)->nullable();
            $table->decimal('pagamento_comissao_venda', 12, 2)->nullable();
            $table->decimal('marketing_lancamento', 12, 2)->nullable();
            $table->integer('marketing_inicio_antes_lancamento')->nullable();
            $table->decimal('medicao_contratacao', 12, 2)->nullable();
            $table->decimal('taxa_juros_pj', 12, 4)->nullable();
            $table->integer('carencia_pj_meses')->nullable();
            $table->integer('amortizacao_pj_parcelas')->nullable();
        });
    }
};
