<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn([
                'imposto_tributos',
                'imposto_iss',
                'imposto_outros',
                'pj_taxaJuros',
                'custo_contratacaoCef',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->decimal('imposto_tributos', 15, 2)->nullable()->after('correcao_anualPosChave');
            $table->decimal('imposto_iss', 15, 2)->nullable()->after('imposto_tributos');
            $table->decimal('imposto_outros', 15, 2)->nullable()->after('imposto_iss');
            $table->decimal('pj_taxaJuros', 15, 2)->nullable()->after('custo_contratacaoCef');
            $table->string('custo_contratacaoCef')->nullable()->after('marketing_lancamento');
        });
    }
};
