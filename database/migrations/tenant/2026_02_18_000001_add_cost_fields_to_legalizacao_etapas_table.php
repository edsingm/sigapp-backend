<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legalizacao_etapas', function (Blueprint $table) {
            if (! Schema::hasColumn('legalizacao_etapas', 'tipo_custo')) {
                $table->string('tipo_custo', 120)->nullable();
            }

            if (! Schema::hasColumn('legalizacao_etapas', 'valor_custo')) {
                $table->decimal('valor_custo', 12, 2)->nullable();
            }

            if (! Schema::hasColumn('legalizacao_etapas', 'custo_pago')) {
                $table->boolean('custo_pago')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('legalizacao_etapas', function (Blueprint $table) {
            if (Schema::hasColumn('legalizacao_etapas', 'custo_pago')) {
                $table->dropColumn('custo_pago');
            }

            if (Schema::hasColumn('legalizacao_etapas', 'valor_custo')) {
                $table->dropColumn('valor_custo');
            }

            if (Schema::hasColumn('legalizacao_etapas', 'tipo_custo')) {
                $table->dropColumn('tipo_custo');
            }
        });
    }
};
