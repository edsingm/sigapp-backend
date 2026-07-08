<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comite_ai_dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comite_revisao_id')->constrained('comite_revisoes')->cascadeOnDelete();
            $table->foreignId('terreno_id')->constrained('terrenos')->cascadeOnDelete();
            $table->foreignId('viabilidade_id')->nullable()->constrained('viabilidades')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('prompt_version')->default(1);
            $table->string('input_hash', 64);
            $table->json('sections')->nullable();
            $table->text('raw_response')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique('comite_revisao_id');
            $table->index(['status', 'updated_at']);
            $table->index(['terreno_id', 'input_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comite_ai_dossiers');
    }
};
