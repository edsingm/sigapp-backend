<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premissas_viabilidade', function (Blueprint $table) {
            $table->dropColumn([
                'avaliacao_lotes_cef',
            ]);
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn([
                'incorp_ri',
                'incorp_entrega',
                'incorp_ateLancamento',
                'obra_ateLancamento',
                'gastos_mensaisStand',
                'comissao_house',
                'porcentagem_comissaoHouse',
                'porcentagem_comissaoImobs',
                'pagto_comissaoNaVenda',
                'marketing_antesLancamento',
                'marketing_lancamento',
                'pj_carenciaPosObra',
                'pj_qtdeParcelasPosCarencia',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('premissas_viabilidade', function (Blueprint $table) {
            $table->json('avaliacao_lotes_cef')->nullable()->after('taxa_exposicao_aplicada');
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->decimal('incorp_ri', 15, 2)->nullable()->after('curva_vendas');
            $table->decimal('incorp_entrega', 15, 2)->nullable()->after('incorp_ri');
            $table->decimal('incorp_ateLancamento', 15, 2)->nullable()->after('incorp_entrega');
            $table->decimal('obra_ateLancamento', 15, 2)->nullable()->after('incorp_ateLancamento');
            $table->decimal('gastos_mensaisStand', 15, 2)->nullable()->after('porcentagem_ConstrucaoStand');
            $table->decimal('comissao_house', 15, 2)->nullable()->after('gastos_mensaisStand');
            $table->decimal('porcentagem_comissaoHouse', 15, 2)->nullable()->after('comissao_house');
            $table->decimal('porcentagem_comissaoImobs', 15, 2)->nullable()->after('porcentagem_comissaoHouse');
            $table->decimal('pagto_comissaoNaVenda', 15, 2)->nullable()->after('porcentagem_comissaoImobs');
            $table->string('marketing_antesLancamento')->nullable()->after('pagto_comissaoNaVenda');
            $table->decimal('marketing_lancamento', 15, 2)->nullable()->after('marketing_antesLancamento');
            $table->string('pj_carenciaPosObra')->nullable()->after('pj_taxaJuros');
            $table->string('pj_qtdeParcelasPosCarencia')->nullable()->after('pj_carenciaPosObra');
        });
    }
};
