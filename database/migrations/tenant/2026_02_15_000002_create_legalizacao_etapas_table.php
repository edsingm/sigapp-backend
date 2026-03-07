<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legalizacao_etapas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legalizacao_id')->constrained('legalizacoes')->onDelete('cascade');
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->enum('status', ['pendente', 'em_andamento', 'concluida', 'bloqueada', 'atrasada'])->default('pendente');
            $table->date('inicio_planejado');
            $table->date('fim_planejado');
            $table->date('inicio_real')->nullable();
            $table->date('fim_real')->nullable();
            $table->unsignedInteger('percentual')->default(0);
            $table->foreignId('responsavel_id')->nullable()->constrained('users');
            $table->string('cor')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['legalizacao_id', 'ordem']);
            $table->index('status');
            $table->index('responsavel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legalizacao_etapas');
    }
};
