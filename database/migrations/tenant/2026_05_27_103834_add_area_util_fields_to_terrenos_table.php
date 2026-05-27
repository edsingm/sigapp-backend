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
        Schema::table('terrenos', function (Blueprint $table) {
            $table->decimal('area_total', 12, 2)->nullable()->after('area_calculada');
            $table->decimal('area_declividade', 12, 2)->nullable()->after('area_total');
            $table->decimal('area_app', 12, 2)->nullable()->after('area_declividade');
            $table->decimal('area_util', 12, 2)->nullable()->after('area_app');
            $table->decimal('percentual_aproveitamento', 5, 2)->nullable()->after('area_util');
            $table->timestamp('area_calculada_em')->nullable()->after('percentual_aproveitamento');
            $table->string('area_calculo_status')->nullable()->after('area_calculada_em');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terrenos', function (Blueprint $table) {
            $table->dropColumn([
                'area_total',
                'area_declividade',
                'area_app',
                'area_util',
                'percentual_aproveitamento',
                'area_calculada_em',
                'area_calculo_status',
            ]);
        });
    }
};
