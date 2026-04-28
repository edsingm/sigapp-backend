<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viabilidades', function (Blueprint $table) {
            $table->decimal('custo_contratacao_cef', 12, 2)->nullable()->after('custo_registro')
                ->comment('Taxa de contratação CEF - valor fixo pago uma vez no 1o mês de lançamento');
            $table->decimal('custo_medicao_cef', 12, 2)->nullable()->after('custo_contratacao_cef')
                ->comment('Taxa de medição CEF - valor fixo mensal durante prazo de obra');
        });
    }

    public function down(): void
    {
        Schema::table('viabilidades', function (Blueprint $table) {
            $table->dropColumn(['custo_contratacao_cef', 'custo_medicao_cef']);
        });
    }
};
