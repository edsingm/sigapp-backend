<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comite_meeting_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comite_revisao_id')->constrained('comite_revisoes')->cascadeOnDelete();
            $table->string('title');
            $table->dateTime('scheduled_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('meeting_mode')->default('online');
            $table->string('location')->nullable();
            $table->foreignId('chair_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['comite_revisao_id', 'status']);
            $table->index('scheduled_at');
        });

        Schema::create('comite_meeting_agenda_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('comite_meeting_sessions')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('decision_required')->default(false);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['session_id', 'position']);
        });

        Schema::create('comite_meeting_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('comite_meeting_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('role')->default('participant');
            $table->string('attendance_status')->default('invited');
            $table->dateTime('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'user_id']);
            $table->index(['session_id', 'attendance_status']);
        });

        Schema::create('comite_meeting_minutes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->unique()->constrained('comite_meeting_sessions')->cascadeOnDelete();
            $table->longText('summary')->nullable();
            $table->json('decisions')->nullable();
            $table->json('blockers')->nullable();
            $table->text('next_steps')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comite_meeting_minutes');
        Schema::dropIfExists('comite_meeting_participants');
        Schema::dropIfExists('comite_meeting_agenda_items');
        Schema::dropIfExists('comite_meeting_sessions');
    }
};
