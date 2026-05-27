<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terrenos', function (Blueprint $table) {
            $table->string('declividade_classificacao')->nullable()->after('area_declividade');
            $table->text('declividade_avaliacao')->nullable()->after('declividade_classificacao');
            $table->string('declividade_impacto_custo')->nullable()->after('declividade_avaliacao');
            $table->decimal('declividade_percentual_maximo', 5, 2)->nullable()->after('declividade_impacto_custo');
            $table->decimal('declividade_percentual_medio', 5, 2)->nullable()->after('declividade_percentual_maximo');
        });
    }

    public function down(): void
    {
        Schema::table('terrenos', function (Blueprint $table) {
            $table->dropColumn([
                'declividade_classificacao',
                'declividade_avaliacao',
                'declividade_impacto_custo',
                'declividade_percentual_maximo',
                'declividade_percentual_medio',
            ]);
        });
    }
};
