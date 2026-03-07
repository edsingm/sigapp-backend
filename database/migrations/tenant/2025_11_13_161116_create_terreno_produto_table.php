<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('terreno_produtos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('terreno_id')->constrained('terrenos')->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable();

            $table->integer('unidades')->nullable();
            $table->decimal('valor', 12, 2)->nullable();
            $table->integer('permuta')->nullable()->default(null);
            $table->decimal('pgto_por_lote', 12, 2)->nullable()->default(null);
            $table->text('observacoes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();

            $table->timestamps();

            $table->index(['terreno_id']);
            $table->index(['produto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terreno_produtos');
    }
};
