<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legalizacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->constrained('terrenos')->onDelete('cascade');
            $table->string('nome');
            $table->enum('status', ['planejado', 'em_andamento', 'concluido', 'cancelado'])->default('planejado');
            $table->date('data_inicio_planejada')->nullable();
            $table->date('data_fim_planejada')->nullable();
            $table->date('data_inicio_real')->nullable();
            $table->date('data_fim_real')->nullable();
            $table->unsignedInteger('percentual_concluido')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->index('terreno_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legalizacoes');
    }
};
