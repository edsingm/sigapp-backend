<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legalizacoes', function (Blueprint $table) {
            if (!Schema::hasColumn('legalizacoes', 'responsavel_id')) {
                $table->foreignId('responsavel_id')->nullable()->constrained('users')->onDelete('set null');
                $table->index('responsavel_id');
            }
            if (!Schema::hasColumn('legalizacoes', 'data_inicio_prevista')) {
                $table->date('data_inicio_prevista')->nullable();
            }
            if (!Schema::hasColumn('legalizacoes', 'data_conclusao_prevista')) {
                $table->date('data_conclusao_prevista')->nullable();
            }
            if (!Schema::hasColumn('legalizacoes', 'custo_total_previsto')) {
                $table->decimal('custo_total_previsto', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('legalizacoes', 'observacoes')) {
                $table->text('observacoes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('legalizacoes', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('legalizacoes', 'responsavel_id')) {
                $table->dropIndex(['responsavel_id']);
                $table->dropForeign(['responsavel_id']);
                $columnsToDrop[] = 'responsavel_id';
            }
            if (Schema::hasColumn('legalizacoes', 'data_inicio_prevista')) {
                $columnsToDrop[] = 'data_inicio_prevista';
            }
            if (Schema::hasColumn('legalizacoes', 'data_conclusao_prevista')) {
                $columnsToDrop[] = 'data_conclusao_prevista';
            }
            if (Schema::hasColumn('legalizacoes', 'custo_total_previsto')) {
                $columnsToDrop[] = 'custo_total_previsto';
            }
            if (Schema::hasColumn('legalizacoes', 'observacoes')) {
                $columnsToDrop[] = 'observacoes';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
