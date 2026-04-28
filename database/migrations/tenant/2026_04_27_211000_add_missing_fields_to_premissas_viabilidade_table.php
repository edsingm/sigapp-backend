<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premissas_viabilidade', function (Blueprint $table) {
            $table->integer('carencia_pj_meses')->default(6)->after('taxa_juros_pj');
            $table->integer('amortizacao_pj_parcelas')->default(18)->after('carencia_pj_meses');
            $table->integer('marketing_inicio_antes_lancamento')->default(3)->after('marketing');
            $table->decimal('obra_ate_lancamento', 10, 4)->default(1.0)->after('incorp_ate_lancamento');
            $table->decimal('incorp_ri', 10, 4)->default(30.0)->after('incorporacao');
            $table->decimal('incorp_entrega', 10, 4)->default(15.0)->after('incorp_ri');
            $table->decimal('incorp_ate_lancamento', 10, 4)->default(80.0)->after('incorp_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('premissas_viabilidade', function (Blueprint $table) {
            $table->dropColumn([
                'carencia_pj_meses',
                'amortizacao_pj_parcelas',
                'marketing_inicio_antes_lancamento',
                'obra_ate_lancamento',
                'incorp_ri',
                'incorp_entrega',
                'incorp_ate_lancamento',
            ]);
        });
    }
};
