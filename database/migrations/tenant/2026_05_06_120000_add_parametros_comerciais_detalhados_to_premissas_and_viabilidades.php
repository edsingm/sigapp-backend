<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premissas_viabilidade', function (Blueprint $table) {
            $table->decimal('gastos_mensais_stand', 8, 4)->default(0.0001)->after('mobilia_decoracao');
            $table->decimal('comissao_house_percentual', 8, 2)->default(3.00)->after('gastos_mensais_stand');
            $table->decimal('comissao_imobiliarias_percentual', 8, 2)->default(3.50)->after('comissao_house_percentual');
            $table->decimal('percentual_vendas_house', 8, 2)->default(50.00)->after('comissao_imobiliarias_percentual');
            $table->decimal('pagamento_comissao_venda', 8, 2)->default(50.00)->after('bonus_equipe_comercial');
            $table->decimal('marketing_lancamento', 8, 2)->default(25.00)->after('marketing');
        });

        Schema::table('viabilidades', function (Blueprint $table) {
            $table->decimal('gastos_mensais_stand', 8, 4)->nullable()->after('mobilia_decoracao');
            $table->decimal('comissao_house_percentual', 8, 2)->nullable()->after('gastos_mensais_stand');
            $table->decimal('comissao_imobiliarias_percentual', 8, 2)->nullable()->after('comissao_house_percentual');
            $table->decimal('percentual_vendas_house', 8, 2)->nullable()->after('comissao_imobiliarias_percentual');
            $table->decimal('pagamento_comissao_venda', 8, 2)->nullable()->after('bonus_equipe_comercial');
            $table->decimal('marketing_lancamento', 8, 2)->nullable()->after('marketing');
            $table->integer('marketing_inicio_antes_lancamento')->nullable()->after('marketing_lancamento');
        });
    }

    public function down(): void
    {
        Schema::table('premissas_viabilidade', function (Blueprint $table) {
            $table->dropColumn([
                'gastos_mensais_stand',
                'comissao_house_percentual',
                'comissao_imobiliarias_percentual',
                'percentual_vendas_house',
                'pagamento_comissao_venda',
                'marketing_lancamento',
            ]);
        });

        Schema::table('viabilidades', function (Blueprint $table) {
            $table->dropColumn([
                'gastos_mensais_stand',
                'comissao_house_percentual',
                'comissao_imobiliarias_percentual',
                'percentual_vendas_house',
                'pagamento_comissao_venda',
                'marketing_lancamento',
                'marketing_inicio_antes_lancamento',
            ]);
        });
    }
};
