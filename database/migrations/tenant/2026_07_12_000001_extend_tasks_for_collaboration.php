<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->json('tags')->nullable()->after('priority');
            $table->index(['assigned_to', 'status', 'due_date']);
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('task_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_task_id']);
        });

        Schema::create('comment_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comment_id')->constrained('comments')->cascadeOnDelete();
            $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['comment_id', 'mentioned_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_mentions');
        Schema::dropIfExists('task_dependencies');

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['assigned_to', 'status', 'due_date']);
            $table->dropIndex(['related_type', 'related_id']);
            $table->dropColumn('tags');
        });
    }
};
