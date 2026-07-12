<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projeto_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projeto_id')->constrained('projetos')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->date('predicted_start')->nullable();
            $table->date('predicted_end')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('weight')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_critical')->default(false);
            $table->timestamps();

            $table->index(['projeto_id', 'position']);
            $table->index(['projeto_id', 'status']);
        });

        Schema::create('projeto_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projeto_id')->constrained('projetos')->cascadeOnDelete();
            $table->foreignId('predecessor_milestone_id')->constrained('projeto_milestones')->cascadeOnDelete();
            $table->foreignId('successor_milestone_id')->constrained('projeto_milestones')->cascadeOnDelete();
            $table->string('dependency_type')->default('finish_to_start');
            $table->integer('lag_days')->default(0);
            $table->timestamps();

            $table->unique(['predecessor_milestone_id', 'successor_milestone_id']);
            $table->index(['projeto_id', 'successor_milestone_id']);
        });

        Schema::create('projeto_risks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projeto_id')->constrained('projetos')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('probability')->default('medium');
            $table->string('impact')->default('medium');
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->text('mitigation')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index(['projeto_id', 'status']);
            $table->index(['projeto_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projeto_risks');
        Schema::dropIfExists('projeto_dependencies');
        Schema::dropIfExists('projeto_milestones');
    }
};
