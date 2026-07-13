<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_context_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('intent');
            $table->json('parameters')->nullable();
            $table->string('input_hash');
            $table->json('output')->nullable();
            $table->string('status')->default('proposed');
            $table->string('action')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('justification')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id', 'status']);
            $table->index(['created_by', 'input_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_context_recommendations');
    }
};
