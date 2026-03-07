<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legalizacao_etapas', function (Blueprint $table) {
            if (!Schema::hasColumn('legalizacao_etapas', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->constrained('legalizacao_etapas')
                    ->nullOnDelete();
                $table->index('parent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('legalizacao_etapas', function (Blueprint $table) {
            if (Schema::hasColumn('legalizacao_etapas', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropIndex(['parent_id']);
                $table->dropColumn('parent_id');
            }
        });
    }
};
