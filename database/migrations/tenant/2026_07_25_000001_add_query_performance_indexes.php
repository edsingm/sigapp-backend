<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_request_logs', function (Blueprint $table): void {
            $table->index('created_at', 'ai_request_logs_created_at_idx');
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->index(
                ['related_type', 'related_id', 'created_at'],
                'comments_related_created_at_idx',
            );
        });

        Schema::table('entity_activities', function (Blueprint $table): void {
            $table->index(
                ['terreno_id', 'happened_at', 'id'],
                'entity_activities_terreno_happened_idx',
            );
        });

        Schema::table('terrenos', function (Blueprint $table): void {
            $table->index(
                ['workflow_status_code', 'created_at', 'id'],
                'terrenos_workflow_created_at_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('terrenos', function (Blueprint $table): void {
            $table->dropIndex('terrenos_workflow_created_at_idx');
        });

        Schema::table('entity_activities', function (Blueprint $table): void {
            $table->dropIndex('entity_activities_terreno_happened_idx');
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->dropIndex('comments_related_created_at_idx');
        });

        Schema::table('ai_request_logs', function (Blueprint $table): void {
            $table->dropIndex('ai_request_logs_created_at_idx');
        });
    }
};
