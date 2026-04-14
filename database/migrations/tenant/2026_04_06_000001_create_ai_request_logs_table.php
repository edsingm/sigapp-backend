<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Migrations\AiMigration;

return new class extends AiMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('conversation_id', 36)->nullable()->index()->comment('UUID da conversa associada');
            $table->string('provider', 50)->nullable()->comment('openrouter, anthropic, etc.');
            $table->string('model', 100)->nullable()->comment('Nome completo do modelo');
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 10, 6)->default(0)->comment('Custo estimado em USD');
            $table->integer('duration_ms')->default(0)->comment('Duração total em milissegundos');
            $table->integer('tool_calls_count')->default(0);
            $table->json('tool_calls')->nullable()->comment('Lista de tools chamadas');
            $table->string('status', 20)->default('success')->index()->comment('success, error, rate_limited');
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_request_logs');
    }
};
