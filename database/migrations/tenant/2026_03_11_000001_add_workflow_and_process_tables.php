<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terrenos', function (Blueprint $table) {
            $table->string('workflow_stage')->nullable()->after('regional_id');
            $table->string('workflow_status_code')->nullable()->after('workflow_stage');
            $table->timestamp('workflow_status_changed_at')->nullable()->after('workflow_status_code');
            $table->string('workflow_reason_code')->nullable()->after('workflow_status_changed_at');
            $table->text('workflow_reason_notes')->nullable()->after('workflow_reason_code');
            $table->json('qualification_data')->nullable()->after('workflow_reason_notes');
            $table->timestamp('qualification_completed_at')->nullable()->after('qualification_data');
            $table->foreignId('qualification_completed_by')->nullable()->after('qualification_completed_at')->constrained('users')->nullOnDelete();

            $table->index(['workflow_stage']);
            $table->index(['workflow_status_code']);
        });

        Schema::table('viabilidades', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('terreno_id');
            $table->boolean('is_current')->default(true)->after('version');
            $table->timestamp('submitted_at')->nullable()->after('approval_notes');
            $table->timestamp('locked_at')->nullable()->after('submitted_at');

            $table->index(['terreno_id', 'version']);
            $table->index(['terreno_id', 'is_current']);
        });

        Schema::table('legalizacao_etapas', function (Blueprint $table) {
            $table->string('phase_code')->nullable()->after('parent_id');
            $table->string('subphase_code')->nullable()->after('phase_code');
            $table->boolean('is_required')->default(true)->after('subphase_code');
            $table->boolean('is_critical')->default(false)->after('is_required');
        });

        Schema::create('terreno_contatos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->constrained('terrenos')->cascadeOnDelete();
            $table->string('nome');
            $table->string('cargo')->nullable();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['terreno_id']);
        });

        Schema::create('viabilidade_secoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viabilidade_id')->constrained('viabilidades')->cascadeOnDelete();
            $table->string('section_code');
            $table->string('section_name');
            $table->json('content_json')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique(['viabilidade_id', 'section_code']);
        });

        Schema::create('viabilidade_aprovacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viabilidade_id')->constrained('viabilidades')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision');
            $table->text('comments')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['viabilidade_id', 'decision']);
        });

        Schema::create('comite_revisoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->constrained('terrenos')->cascadeOnDelete();
            $table->foreignId('viabilidade_id')->constrained('viabilidades')->cascadeOnDelete();
            $table->string('status')->default('aguardando_comite');
            $table->string('final_decision')->nullable();
            $table->text('final_comments')->nullable();
            $table->json('required_departments')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['terreno_id', 'status']);
        });

        Schema::create('comite_pareceres_departamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comite_revisao_id')->constrained('comite_revisoes')->cascadeOnDelete();
            $table->string('department_code');
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision');
            $table->text('comments')->nullable();
            $table->boolean('checklist_completed')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['comite_revisao_id', 'department_code']);
        });

        Schema::create('comite_pendencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comite_revisao_id')->constrained('comite_revisoes')->cascadeOnDelete();
            $table->foreignId('terreno_id')->constrained('terrenos')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->string('department_code')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('negociacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->constrained('terrenos')->cascadeOnDelete();
            $table->string('status')->default('em_negociacao');
            $table->decimal('proposal_value', 15, 2)->nullable();
            $table->string('business_model')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['terreno_id', 'status']);
        });

        Schema::create('negociacao_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negociacao_id')->constrained('negociacoes')->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload_json')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('happened_at')->nullable();
            $table->timestamps();

            $table->index(['negociacao_id', 'event_type']);
        });

        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->constrained('terrenos')->cascadeOnDelete();
            $table->foreignId('negociacao_id')->nullable()->constrained('negociacoes')->nullOnDelete();
            $table->string('contract_type')->nullable();
            $table->string('contract_number')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('minuta_contratual');
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contrato_partes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->string('name');
            $table->string('document')->nullable();
            $table->string('party_type')->nullable();
            $table->string('signer_name')->nullable();
            $table->string('signer_document')->nullable();
            $table->timestamps();
        });

        Schema::create('legalizacao_pendencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legalizacao_id')->constrained('legalizacoes')->cascadeOnDelete();
            $table->foreignId('legalizacao_etapa_id')->nullable()->constrained('legalizacao_etapas')->nullOnDelete();
            $table->string('title');
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->boolean('is_critical')->default(false);
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('legalizacao_documentos_fase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legalizacao_etapa_id')->constrained('legalizacao_etapas')->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->string('category')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_required')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->constrained('terrenos')->cascadeOnDelete();
            $table->string('old_stage')->nullable();
            $table->string('old_status_code')->nullable();
            $table->string('new_stage');
            $table->string('new_status_code');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason_code')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['terreno_id', 'new_status_code']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->nullable()->constrained('terrenos')->nullOnDelete();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->nullable()->constrained('terrenos')->nullOnDelete();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('entity_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->nullable()->constrained('terrenos')->nullOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('summary');
            $table->json('payload_json')->nullable();
            $table->timestamp('happened_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_activities');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('status_histories');
        Schema::dropIfExists('legalizacao_documentos_fase');
        Schema::dropIfExists('legalizacao_pendencias');
        Schema::dropIfExists('contrato_partes');
        Schema::dropIfExists('contratos');
        Schema::dropIfExists('negociacao_eventos');
        Schema::dropIfExists('negociacoes');
        Schema::dropIfExists('comite_pendencias');
        Schema::dropIfExists('comite_pareceres_departamento');
        Schema::dropIfExists('comite_revisoes');
        Schema::dropIfExists('viabilidade_aprovacoes');
        Schema::dropIfExists('viabilidade_secoes');
        Schema::dropIfExists('terreno_contatos');

        Schema::table('legalizacao_etapas', function (Blueprint $table) {
            $table->dropColumn(['phase_code', 'subphase_code', 'is_required', 'is_critical']);
        });

        Schema::table('viabilidades', function (Blueprint $table) {
            $table->dropColumn(['version', 'is_current', 'submitted_at', 'locked_at']);
        });

        Schema::table('terrenos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qualification_completed_by');
            $table->dropColumn([
                'workflow_stage',
                'workflow_status_code',
                'workflow_status_changed_at',
                'workflow_reason_code',
                'workflow_reason_notes',
                'qualification_data',
                'qualification_completed_at',
            ]);
        });
    }
};
