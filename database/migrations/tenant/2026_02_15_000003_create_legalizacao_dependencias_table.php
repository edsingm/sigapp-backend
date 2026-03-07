<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legalizacao_dependencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legalizacao_id')->constrained('legalizacoes')->onDelete('cascade');
            $table->foreignId('etapa_origem_id')->constrained('legalizacao_etapas')->onDelete('cascade');
            $table->foreignId('etapa_destino_id')->constrained('legalizacao_etapas')->onDelete('cascade');
            $table->enum('tipo', ['FS'])->default('FS');
            $table->timestamps();

            $table->unique(['etapa_origem_id', 'etapa_destino_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legalizacao_dependencias');
    }
};
