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
        Schema::create('ai_generated_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('terreno_id')->nullable()->constrained('terrenos')->nullOnDelete();
            $table->string('nome');
            $table->string('file_path');
            $table->unsignedBigInteger('tamanho');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['terreno_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generated_reports');
    }
};
