<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viabilidade_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('viabilidade_id')->constrained('viabilidades')->cascadeOnDelete();
            $table->string('name');
            $table->string('scenario_type')->default('custom');
            $table->string('status')->default('draft');
            $table->json('premises_snapshot')->nullable();
            $table->json('results')->nullable();
            $table->string('formula_version')->default('viabilidade-v1');
            $table->string('input_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('promoted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('promoted_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['viabilidade_id', 'status']);
            $table->index(['viabilidade_id', 'scenario_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viabilidade_scenarios');
    }
};
