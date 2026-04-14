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
        Schema::create('terreno_documentos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('terreno_id')->constrained('terrenos')->cascadeOnDelete();
            $table->string('nome');
            $table->string('tipo')->nullable();
            $table->string('categoria')->nullable();
            $table->text('descricao')->nullable();
            $table->string('url')->nullable();
            $table->unsignedBigInteger('tamanho')->nullable();
            $table->string('status')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['terreno_id']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terreno_documentos');
    }
};
