<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negociacao_ofertas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('negociacao_id')->constrained('negociacoes')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('offer_type')->default('proposal');
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('business_model')->nullable();
            $table->json('terms')->nullable();
            $table->string('status')->default('draft');
            $table->date('valid_until')->nullable();
            $table->foreignId('previous_offer_id')->nullable()->constrained('negociacao_ofertas')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(['negociacao_id', 'version']);
            $table->index(['negociacao_id', 'status']);
        });

        Schema::create('negociacao_aprovacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('negociacao_id')->constrained('negociacoes')->cascadeOnDelete();
            $table->string('area');
            $table->string('decision')->default('pending');
            $table->text('comment')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['negociacao_id', 'area']);
            $table->index(['negociacao_id', 'decision']);
        });

        Schema::create('contrato_condicoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');
            // A fundação documental tenant ainda está em evolução; manter a referência
            // opcional sem FK permite ligar o documento quando ela estiver disponível.
            $table->unsignedBigInteger('evidence_document_id')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->index(['contrato_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_condicoes');
        Schema::dropIfExists('negociacao_aprovacoes');
        Schema::dropIfExists('negociacao_ofertas');
    }
};
