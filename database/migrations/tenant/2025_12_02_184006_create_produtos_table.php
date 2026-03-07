<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('private_area', 15, 2)->nullable();
            $table->decimal('m2_cost', 15, 2)->nullable();
            $table->decimal('infra_cost', 15, 2)->nullable();
            $table->string('status')->default('ativo');
            $table->decimal('sinal', 15, 2)->nullable();
            $table->decimal('parcela_obra', 15, 2)->nullable();
            $table->decimal('parcela_posChave', 15, 2)->nullable();
            $table->string('qtde_parcelas_posChave')->nullable();
            $table->decimal('demanda_minCef', 15, 2)->nullable();
            $table->string('defasagem_pgtoTerreno')->nullable();
            $table->decimal('avaliacao_lotesCef', 15, 2)->nullable();
            $table->decimal('juros_mensalSinal', 15, 2)->nullable();
            $table->decimal('juros_mensalObra', 15, 2)->nullable();
            $table->decimal('juros_mensalPosChave', 15, 2)->nullable();
            $table->decimal('correcao_anualSinal', 15, 2)->nullable();
            $table->decimal('correcao_anualObra', 15, 2)->nullable();
            $table->decimal('correcao_anualPosChave', 15, 2)->nullable();
            $table->decimal('imposto_tributos', 15, 2)->nullable();
            $table->decimal('imposto_iss', 15, 2)->nullable();
            $table->decimal('imposto_outros', 15, 2)->nullable();
            $table->json('curva_vendas')->nullable();
            $table->decimal('incorp_ri', 15, 2)->nullable();
            $table->decimal('incorp_entrega', 15, 2)->nullable();
            $table->decimal('incorp_ateLancamento', 15, 2)->nullable();
            $table->decimal('obra_ateLancamento', 15, 2)->nullable();
            $table->decimal('assist_tecnica1', 15, 2)->nullable();
            $table->decimal('assist_tecnica2', 15, 2)->nullable();
            $table->decimal('assist_tecnica3', 15, 2)->nullable();
            $table->decimal('assist_tecnica4', 15, 2)->nullable();
            $table->decimal('assist_tecnica5', 15, 2)->nullable();
            $table->string('meses_inicioConstrucao')->nullable();
            $table->decimal('porcentagem_ConstrucaoStand', 15, 2)->nullable();
            $table->decimal('gastos_mensaisStand', 15, 2)->nullable();
            $table->decimal('comissao_house', 15, 2)->nullable();
            $table->decimal('porcentagem_comissaoHouse', 15, 2)->nullable();
            $table->decimal('porcentagem_comissaoImobs', 15, 2)->nullable();
            $table->decimal('pagto_comissaoNaVenda', 15, 2)->nullable();
            $table->string('marketing_antesLancamento')->nullable();
            $table->decimal('marketing_lancamento', 15, 2)->nullable();
            $table->string('custo_contratacaoCef')->nullable();
            $table->decimal('pj_taxaJuros', 15, 2)->nullable();
            $table->string('pj_carenciaPosObra')->nullable();
            $table->string('pj_qtdeParcelasPosCarencia')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};

