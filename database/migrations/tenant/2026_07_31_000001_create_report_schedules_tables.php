<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_template_id')->constrained('report_templates')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('frequency'); // daily|weekly|monthly
            $table->string('format')->default('xlsx');
            $table->json('filters')->nullable();
            $table->boolean('notify_email')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('last_run_id')->nullable()->constrained('report_runs')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
            $table->index(['owner_id', 'is_active']);
        });

        Schema::table('report_runs', function (Blueprint $table): void {
            $table->foreignId('report_schedule_id')->nullable()->after('report_template_id')
                ->constrained('report_schedules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('report_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('report_schedule_id');
        });
        Schema::dropIfExists('report_schedules');
    }
};
